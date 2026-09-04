<?php

namespace App\Actions;

use App\Assessment\AssessmentPriceInputResolver;
use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Assessment\Price\Price;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateAssessmentForPermitApplication
{
    public function __construct(
        private BusinessPermitEvaluationReadiness $evaluationReadiness,
        private PermitApplicationStatusMutation $statusMutation,
        private AssessmentPriceInputResolver $priceInputResolver,
        private CanonicalFinancialFingerprint $financialFingerprint,
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

            $priceInput = $this->priceInputResolver->resolve($permitApplication, $evaluationProjection);
            $inputSnapshot = $priceInput->toArray();
            $inputFingerprint = $this->financialFingerprint->hash($inputSnapshot);
            $resolvedPrice = Price::fromInput($priceInput)->resolve();
            $reportSnapshot = Price::fromInput($priceInput)->report()->toArray();
            $reportFingerprint = $this->financialFingerprint->hash($reportSnapshot);

            $assessment = $permitApplication->assessments()->create([
                'business_permit_evaluation_version_id' => $evaluationProjection['version_id'] ?? null,
                'business_permit_evaluation_fingerprint' => $evaluationProjection['current_fingerprint'] ?? null,
                'assessed_by_id' => $assessedBy?->id,
                'sequence' => ($permitApplication->assessments()->max('sequence') ?? 0) + 1,
                'status' => AssessmentStatus::Computed,
                'assessed_at' => now(),
                'source_snapshot' => $this->sourceSnapshot($permitApplication, $evaluationProjection),
                'total_amount_cents' => $resolvedPrice->totalMinor(),
                'currency' => $resolvedPrice->currency,
                'assessment_price_input_snapshot' => $inputSnapshot,
                'assessment_price_input_fingerprint' => $inputFingerprint,
                'price_report_snapshot' => $reportSnapshot,
                'price_report_fingerprint' => $reportFingerprint,
            ]);

            $this->persistResolvedPrice($assessment, $resolvedPrice->components);
            $lineTotal = (int) $assessment->lines()->sum('amount_cents');
            if ($lineTotal !== $resolvedPrice->totalMinor()
                || data_get($reportSnapshot, 'total.minor') !== $resolvedPrice->totalMinor()) {
                throw new LogicException('Assessment, lines, ResolvedPrice, and PriceReport totals must be identical.');
            }

            $this->statusMutation->persistStatusConsequence($permitApplication, PermitApplicationStatus::Assessment, [
                'assessed_at' => $assessment->assessed_at,
            ]);

            return $assessment->load('lines');
        });
    }

    /** @param list<array<string, mixed>> $components */
    private function persistResolvedPrice(Assessment $assessment, array $components): void
    {
        foreach ($components as $component) {
            $explanation = $component['explanation'];
            $assessment->lines()->create([
                'permit_application_line_id' => $component['permit_application_line_id'],
                'fee_rule_id' => $explanation['fee_rule_id'] ?? null,
                'business_permit_evaluation_item_id' => $explanation['business_permit_evaluation_item_id'] ?? null,
                'paperless_payment_order_line_id' => $explanation['paperless_payment_order_line_id'] ?? null,
                'line_of_business_id' => $component['line_of_business_id'],
                'code' => $component['key'],
                'name' => $component['label'],
                'category' => $explanation['category'],
                'calculation_type' => $explanation['calculation_type'],
                'basis' => $explanation['basis'],
                'basis_amount_cents' => $explanation['basis_amount_minor'],
                'amount_cents' => $component['resolved_minor'],
                'legal_basis' => $component['legal_basis'],
                'rule_snapshot' => [
                    ...$explanation['rule_snapshot'],
                    'price_component' => [
                        'schema_version' => 'bpls.price-component.v1',
                        'currency' => $component['currency'],
                        'scheduled_amount_minor' => $component['scheduled_minor'],
                        'resolved_amount_minor' => $component['resolved_minor'],
                        'exact_once_key' => $component['exact_once_key'],
                        'source_version' => $component['source_version'],
                        'applied_modifier_keys' => $component['applied_modifier_keys'],
                    ],
                ],
            ]);
        }
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
