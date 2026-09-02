<?php

namespace App\Actions;

use App\Assessment\ApplicableFeeRuleQuery;
use App\Assessment\AssessmentCalculator;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\FeeRule;
use App\Models\PaperlessPaymentOrder;
use App\Models\PaperlessPaymentOrderLine;
use App\Models\PermitApplication;
use App\Models\PermitApplicationLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateAssessmentForPermitApplication
{
    public function __construct(
        private AssessmentCalculator $calculator,
        private ApplicableFeeRuleQuery $applicableFeeRuleQuery,
        private BusinessPermitEvaluationReadiness $evaluationReadiness,
        private PermitApplicationStatusMutation $statusMutation,
    ) {}

    public function handle(PermitApplication $permitApplication, ?User $assessedBy = null): Assessment
    {
        return DB::transaction(function () use ($permitApplication, $assessedBy): Assessment {
            $permitApplication->loadMissing(['business', 'lines.lineOfBusiness']);

            $evaluation = $permitApplication->businessPermitEvaluation()->with('currentVersion.counterCheck')->first();
            $evaluationProjection = null;

            if ($evaluation instanceof BusinessPermitEvaluation) {
                $evaluationMode = $this->evaluationMode($permitApplication);
                $readiness = $this->evaluationReadiness->forAssessment($evaluation, $evaluationMode);
                if (! $readiness['ready']) {
                    throw new UnsupportedAssessmentPolicy('Business Permit Evaluation is not Ready for Assessment: '.implode(' ', $readiness['issues']));
                }
                $evaluationProjection = $readiness['projection'];
            } else {
                $this->assertProvisionalOfficeChargesReady($permitApplication);
            }

            if ($permitApplication->isHistoricalEvidenceOnly()) {
                throw new LogicException("Historical evidence application [{$permitApplication->id}] cannot enter operational assessment.");
            }

            $this->assertAssessmentMayBeComputed($permitApplication);

            if (is_array($evaluationProjection)) {
                $existingEvaluationAssessment = $permitApplication->assessments()
                    ->where('business_permit_evaluation_version_id', $evaluationProjection['version_id'])
                    ->where('business_permit_evaluation_fingerprint', $evaluationProjection['current_fingerprint'])
                    ->whereNull('superseded_at')
                    ->with('lines')
                    ->first();

                if ($existingEvaluationAssessment instanceof Assessment) {
                    return $existingEvaluationAssessment;
                }
            }

            $permitApplication->assessments()
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $assessment = $permitApplication->assessments()->create([
                'business_permit_evaluation_version_id' => $evaluationProjection['version_id'] ?? null,
                'business_permit_evaluation_fingerprint' => $evaluationProjection['current_fingerprint'] ?? null,
                'assessed_by_id' => $assessedBy?->id,
                'sequence' => ($permitApplication->assessments()->max('sequence') ?? 0) + 1,
                'status' => AssessmentStatus::Computed,
                'assessed_at' => now(),
                'source_snapshot' => $this->sourceSnapshot($permitApplication, $evaluationProjection),
            ]);

            if (is_array($evaluationProjection)) {
                $this->createEvaluationLines($assessment, $evaluationProjection);
            } else {
                $feeRules = $this->applicableFeeRuleQuery->forPermitApplication($permitApplication);
                $this->createApplicationScopedLines($assessment, $feeRules);
                $this->createLineOfBusinessScopedLines($assessment, $permitApplication, $feeRules);
                $this->createOfficeChargeContributionLines($assessment, $permitApplication);
            }

            $assessment->update([
                'total_amount_cents' => (int) $assessment->lines()->sum('amount_cents'),
            ]);

            $this->statusMutation->persistStatusConsequence($permitApplication, PermitApplicationStatus::Assessment, [
                'assessed_at' => $assessment->assessed_at,
            ]);

            return $assessment->load('lines');
        });
    }

    private function assertAssessmentMayBeComputed(PermitApplication $permitApplication): void
    {
        if ($permitApplication->paymentSchedules()->exists()) {
            throw new LogicException('An assessment cannot be recomputed after payment scheduling has begun.');
        }

        if ($permitApplication->provisionalUatPermitCompletion()->whereNotNull('released_at')->exists()) {
            throw new LogicException('A preview-completed permit cannot return to assessment without restoring the sample journey.');
        }

        $currentAssessment = $permitApplication->assessments()
            ->whereNull('superseded_at')
            ->with('decision')
            ->latest('sequence')
            ->first();

        if (! $currentAssessment instanceof Assessment) {
            return;
        }

        if ($currentAssessment->decision === null
            || $currentAssessment->decision->action === AssessmentDecisionAction::ReturnedForCorrection) {
            return;
        }

        throw new LogicException('An assessment with an immutable Treasurer approval cannot be recomputed.');
    }

    /**
     * @param  array<string, mixed>|null  $evaluationProjection
     * @return array<string, mixed>
     */
    private function sourceSnapshot(PermitApplication $permitApplication, ?array $evaluationProjection = null): array
    {
        return [
            'permit_application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $permitApplication->business_id,
            'business_name' => $permitApplication->business->name,
            'line_ids' => $permitApplication->lines->pluck('id')->values()->all(),
            'office_charge_contribution_ids' => $permitApplication->officeChargeContributions()
                ->where('status', 'approved')
                ->orderBy('office_code')
                ->pluck('id')
                ->all(),
            'bplo_routing_determination_id' => $permitApplication->bploRoutingDetermination?->id,
            'paperless_payment_order_ids' => $permitApplication->paperlessPaymentOrders()
                ->where('status', 'issued')
                ->whereNull('superseded_at')
                ->orderBy('id')
                ->pluck('id')
                ->all(),
            'business_permit_evaluation' => $evaluationProjection === null ? null : [
                'evaluation_id' => $evaluationProjection['evaluation_id'],
                'version_id' => $evaluationProjection['version_id'],
                'version_sequence' => $evaluationProjection['version_sequence'],
                'fingerprint' => $evaluationProjection['current_fingerprint'],
                'resolved_line_of_business_ids' => $evaluationProjection['resolved_line_of_business_ids'],
            ],
        ];
    }

    /** @param array<string, mixed> $projection */
    private function createEvaluationLines(Assessment $assessment, array $projection): void
    {
        foreach ($projection['projected_charges'] as $expected) {
            $feeRule = $expected['fee_rule'];
            $applicationLine = $expected['application_line'];
            $calculation = $this->calculator->calculate($feeRule, $applicationLine);

            if ($calculation['amount_cents'] !== $expected['amount_cents']
                || $calculation['basis_amount_cents'] !== $expected['basis_amount_cents']
                || $calculation['rule_snapshot'] !== $expected['rule_snapshot']) {
                throw new UnsupportedAssessmentPolicy("Evaluation and Assessment pricing parity failed for fee rule [{$feeRule->code}].");
            }

            $assessment->lines()->create([
                'permit_application_line_id' => $expected['permit_application_line_id'],
                'fee_rule_id' => $feeRule->id,
                'line_of_business_id' => $expected['line_of_business_id'],
                'code' => $expected['code'],
                'name' => $expected['name'],
                'category' => $expected['category'],
                'calculation_type' => $expected['calculation_type'],
                'basis' => $expected['basis'],
                'basis_amount_cents' => $expected['basis_amount_cents'],
                'amount_cents' => $expected['amount_cents'],
                'legal_basis' => $expected['legal_basis'],
                'rule_snapshot' => [
                    ...$expected['rule_snapshot'],
                    'evaluation_source_classification' => $expected['source_classification'],
                ],
            ]);
        }

        $this->createPaperlessPaymentOrderLines($assessment, $projection);
    }

    /** @param array<string, mixed> $projection */
    private function createPaperlessPaymentOrderLines(Assessment $assessment, array $projection): void
    {
        $items = $projection['items'] ?? [];
        if (! is_array($items)) {
            throw new UnsupportedAssessmentPolicy('Evaluation item projection is invalid.');
        }

        $eligibleRevisionIds = collect($items)
            ->filter(fn (array $item): bool => $item['item_type'] === 'charge'
                && $item['applicability'] === 'applicable'
                && $item['resolution'] === 'resolved')
            ->pluck('revision_id')
            ->filter()
            ->values();

        PaperlessPaymentOrder::query()
            ->where('permit_application_id', $assessment->permit_application_id)
            ->whereIn('business_permit_evaluation_item_revision_id', $eligibleRevisionIds)
            ->where('status', 'issued')
            ->whereNull('superseded_at')
            ->with(['lines', 'routingWork', 'evaluationItemRevision'])
            ->orderBy('id')
            ->get()
            ->each(function (PaperlessPaymentOrder $order) use ($assessment, $projection): void {
                if ((int) $order->lines->sum('amount_cents') !== $order->total_amount_cents) {
                    throw new UnsupportedAssessmentPolicy("Paperless Payment Order [{$order->id}] does not reconcile to its financial lines.");
                }

                $order->lines->each(function (PaperlessPaymentOrderLine $line) use ($assessment, $order, $projection): void {
                    $assessment->lines()->create([
                        'business_permit_evaluation_item_id' => $order->evaluationItemRevision?->business_permit_evaluation_item_id,
                        'paperless_payment_order_line_id' => $line->id,
                        'permit_application_line_id' => $line->permit_application_line_id,
                        'line_of_business_id' => $line->line_of_business_id,
                        'code' => $line->code,
                        'name' => $line->name,
                        'category' => FeeRuleCategory::Fee,
                        'calculation_type' => FeeRuleCalculationType::Fixed,
                        'basis' => 'paperless_payment_order',
                        'basis_amount_cents' => $line->amount_cents,
                        'amount_cents' => $line->amount_cents,
                        'legal_basis' => null,
                        'rule_snapshot' => [
                            'source' => 'paperless_payment_order',
                            'paperless_payment_order_id' => $order->id,
                            'paperless_payment_order_line_id' => $line->id,
                            'office_code' => $order->routingWork->office_code,
                            'office_label' => $order->routingWork->office_label,
                            'bplo_routing_work_id' => $order->bplo_routing_work_id,
                            'evaluation_version_id' => $projection['version_id'],
                            'evaluation_fingerprint' => $projection['current_fingerprint'],
                            'issued_by_id' => $order->issued_by_id,
                            'issued_at' => $order->issued_at->toIso8601String(),
                            'source_snapshot' => $order->source_snapshot,
                        ],
                    ]);
                });
            });
    }

    /**
     * @param  Collection<int, FeeRule>  $feeRules
     */
    private function createApplicationScopedLines(Assessment $assessment, Collection $feeRules): void
    {
        $feeRules
            ->where('scope', FeeRuleScope::Application)
            ->each(fn (FeeRule $feeRule) => $this->createAssessmentLine($assessment, $feeRule));
    }

    /**
     * @param  Collection<int, FeeRule>  $feeRules
     */
    private function createLineOfBusinessScopedLines(Assessment $assessment, PermitApplication $permitApplication, Collection $feeRules): void
    {
        $lineRules = $feeRules->where('scope', FeeRuleScope::LineOfBusiness);

        $permitApplication->lines->each(function (PermitApplicationLine $applicationLine) use ($assessment, $lineRules): void {
            $lineRules
                ->where('line_of_business_id', $applicationLine->line_of_business_id)
                ->each(fn (FeeRule $feeRule) => $this->createAssessmentLine($assessment, $feeRule, $applicationLine));
        });
    }

    private function createAssessmentLine(Assessment $assessment, FeeRule $feeRule, ?PermitApplicationLine $applicationLine = null): void
    {
        $calculation = $this->calculator->calculate($feeRule, $applicationLine);

        $assessment->lines()->create([
            'permit_application_line_id' => $applicationLine?->id,
            'fee_rule_id' => $feeRule->id,
            'line_of_business_id' => $applicationLine instanceof PermitApplicationLine
                ? $applicationLine->line_of_business_id
                : $feeRule->line_of_business_id,
            'code' => $feeRule->code,
            'name' => $feeRule->name,
            'category' => $feeRule->category,
            'calculation_type' => $feeRule->calculation_type,
            'basis' => $feeRule->basis,
            'basis_amount_cents' => $calculation['basis_amount_cents'],
            'amount_cents' => $calculation['amount_cents'],
            'legal_basis' => $feeRule->legal_basis,
            'rule_snapshot' => $calculation['rule_snapshot'],
        ]);
    }

    private function assertProvisionalOfficeChargesReady(PermitApplication $permitApplication): void
    {
        $workflow = data_get($permitApplication->metadata, 'provisional_uat_workflow');

        if (! is_array($workflow) || data_get($workflow, 'semantic_classification') !== 'provisional_uat') {
            return;
        }

        $configuredOfficeCodes = $workflow['applicable_office_codes'] ?? [];
        $requiredOfficeCodes = collect(is_array($configuredOfficeCodes) ? $configuredOfficeCodes : [])->filter()->values();
        $approvedOfficeCodes = $permitApplication->officeChargeContributions()
            ->where('status', 'approved')
            ->pluck('office_code');
        $missingOfficeCodes = $requiredOfficeCodes->diff($approvedOfficeCodes)->values();

        if ($missingOfficeCodes->isNotEmpty()) {
            throw new UnsupportedAssessmentPolicy('Assessment consolidation is waiting for these scenario office reviews: '.$missingOfficeCodes->implode(', ').'.');
        }
    }

    private function createOfficeChargeContributionLines(Assessment $assessment, PermitApplication $permitApplication): void
    {
        $permitApplication->officeChargeContributions()
            ->where('status', 'approved')
            ->where('is_applicable', true)
            ->orderBy('office_code')
            ->get()
            ->each(function ($contribution) use ($assessment): void {
                $assessment->lines()->create([
                    'code' => 'UAT-OFFICE-'.str($contribution->office_code)->upper()->toString(),
                    'name' => $contribution->office_label,
                    'category' => FeeRuleCategory::Fee,
                    'calculation_type' => FeeRuleCalculationType::Fixed,
                    'basis' => 'manual_office_assessment',
                    'basis_amount_cents' => $contribution->amount_cents ?? 0,
                    'amount_cents' => $contribution->amount_cents ?? 0,
                    'legal_basis' => null,
                    'rule_snapshot' => [
                        'semantic_classification' => 'provisional_uat',
                        'source' => 'concerned_office_charge_contribution',
                        'office_charge_contribution_id' => $contribution->id,
                        'office_code' => $contribution->office_code,
                        'submitted_by_id' => $contribution->submitted_by_id,
                        'submitted_at' => $contribution->submitted_at?->toIso8601String(),
                        'generalizes_municipal_policy' => false,
                        'real_taxpayer_liability' => false,
                    ],
                ]);
            });
    }

    private function evaluationMode(PermitApplication $permitApplication): string
    {
        if (data_get($permitApplication->metadata, 'business_permit_evaluation.semantic_classification') !== 'provisional_uat') {
            return 'commissioned';
        }

        if (app()->isProduction()) {
            throw new UnsupportedAssessmentPolicy('provisional_uat Evaluation values cannot establish production taxpayer liability.');
        }

        return 'provisional_uat';
    }
}
