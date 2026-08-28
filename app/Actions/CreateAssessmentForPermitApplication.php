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
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Models\Assessment;
use App\Models\FeeRule;
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
    ) {}

    public function handle(PermitApplication $permitApplication, ?User $assessedBy = null): Assessment
    {
        return DB::transaction(function () use ($permitApplication, $assessedBy): Assessment {
            $permitApplication->loadMissing(['business', 'lines.lineOfBusiness']);

            $this->assertProvisionalOfficeChargesReady($permitApplication);

            if ($permitApplication->isHistoricalEvidenceOnly()) {
                throw new LogicException("Historical evidence application [{$permitApplication->id}] cannot enter operational assessment.");
            }

            $this->assertAssessmentMayBeComputed($permitApplication);

            $permitApplication->assessments()
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $assessment = $permitApplication->assessments()->create([
                'assessed_by_id' => $assessedBy?->id,
                'sequence' => ($permitApplication->assessments()->max('sequence') ?? 0) + 1,
                'status' => AssessmentStatus::Computed,
                'assessed_at' => now(),
                'source_snapshot' => $this->sourceSnapshot($permitApplication),
            ]);

            $feeRules = $this->applicableFeeRuleQuery->forPermitApplication($permitApplication);

            $this->createApplicationScopedLines($assessment, $feeRules);
            $this->createLineOfBusinessScopedLines($assessment, $permitApplication, $feeRules);
            $this->createOfficeChargeContributionLines($assessment, $permitApplication);

            $assessment->update([
                'total_amount_cents' => $assessment->lines()->sum('amount_cents'),
            ]);

            $permitApplication->update([
                'status' => PermitApplicationStatus::Assessment,
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
     * @return array<string, mixed>
     */
    private function sourceSnapshot(PermitApplication $permitApplication): array
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
        ];
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
}
