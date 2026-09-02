<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\TreasuryCounterCheckResult;
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepareBusinessPermitEvaluatorUatDataset
{
    private ?User $bploRoutingActor = null;

    public function __construct(
        private readonly InitializeBusinessPermitEvaluation $initialize,
        private readonly DefineBusinessPermitEvaluationItem $defineItem,
        private readonly CompleteBusinessPermitEvaluationResponsibility $completeResponsibility,
        private readonly RecordBusinessPermitEvaluationCounterCheck $counterCheck,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly CorrectEvaluationLinesOfBusiness $correctLinesOfBusiness,
        private readonly RecordAssessmentDecision $recordAssessmentDecision,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
        private readonly AssessmentSnapshotFingerprint $assessmentFingerprint,
        private readonly StakeholderPreviewSafety $previewSafety,
        private readonly RecordBploRoutingDetermination $recordBploRouting,
        private readonly PermitApplicationStatusMutation $statusMutation,
    ) {}

    /**
     * @param  array{citizen: User, bplo: User, assessment_officer: User, treasury: User, municipal_treasurer: User, engineering: User, mpdo: User, assessor: User, health: User, menro: User}  $actors
     * @return array<string, mixed>
     */
    public function handle(string $runId, array $actors): array
    {
        if (! $this->previewSafety->isEnabled()) {
            throw new RuntimeException('Business Permit Evaluator UAT data is refused outside the canonical stakeholder preview.');
        }

        return DB::transaction(function () use ($runId, $actors): array {
            $this->bploRoutingActor = $actors['bplo'];
            $this->scenarioFeeRule($runId);

            $existing = PermitApplication::query()->where('metadata->business_permit_evaluation->uat_run_id', $runId)->get();
            if ($existing->isNotEmpty()) {
                return $this->inventory($runId, $existing);
            }

            $retail = $this->lineOfBusiness('EVAL-UAT-RETAIL', 'Retail — Evaluator UAT');
            $repair = $this->lineOfBusiness('EVAL-UAT-REPAIR', 'Repair Services — Evaluator UAT');
            $restaurant = $this->lineOfBusiness('EVAL-UAT-RESTAURANT', 'Restaurant — Evaluator UAT');

            $cases = [];
            $cases['just_created'] = $this->case($runId, 'just-created', $actors['citizen'], $retail, $actors['assessment_officer']);

            $cases['interactive_golden'] = $this->financialWorkingPaperCase(
                $runId,
                'interactive-golden',
                $actors,
                $retail,
                $repair,
                $restaurant,
            );
            $cases['completed_assessment_conformance_golden'] = $this->completedAssessmentConformanceGolden(
                $runId,
                $actors,
                $retail,
                $repair,
                $restaurant,
            );

            $cases['awaiting_engineering'] = $this->case($runId, 'awaiting-engineering', $actors['citizen'], $retail, $actors['assessment_officer']);
            $this->officeItem($cases['awaiting_engineering'], 'engineering', $actors['engineering'], 12_500, true);

            $cases['awaiting_health'] = $this->case($runId, 'awaiting-health', $actors['citizen'], $retail, $actors['assessment_officer']);
            $this->officeDetermination($cases['awaiting_health'], 'health', $actors['health'], BusinessPermitEvaluationApplicability::Applicable);

            $cases['office_confirms_default'] = $this->case($runId, 'office-confirms-default', $actors['citizen'], $retail, $actors['assessment_officer']);
            $confirmed = $this->officeItem($cases['office_confirms_default'], 'engineering', $actors['engineering'], 12_500, true);
            $this->complete($confirmed, $actors['engineering'], 12_500, 'Physical synthetic UAT inspection completed.', false);

            $cases['office_override'] = $this->case($runId, 'office-override', $actors['citizen'], $retail, $actors['assessment_officer']);
            $overridden = $this->officeItem($cases['office_override'], 'engineering', $actors['engineering'], 12_500, true);
            $this->complete($overridden, $actors['engineering'], 15_000, 'Synthetic UAT conditions require a different office-resolved amount.', false);

            $cases['accepted_not_applicable'] = $this->case($runId, 'accepted-not-applicable', $actors['citizen'], $retail, $actors['assessment_officer']);
            $notApplicable = $this->officeDetermination($cases['accepted_not_applicable'], 'health', $actors['health'], BusinessPermitEvaluationApplicability::Undetermined);
            $this->completeDetermination($notApplicable, $actors['health'], BusinessPermitEvaluationApplicability::NotApplicable, 'Synthetic UAT accepted Not Applicable determination.');

            $cases['ready_for_assessment'] = $this->readyCase($runId, 'ready-for-assessment', $actors, $retail);

            $cases['assessment_prepared'] = $this->readyCase($runId, 'assessment-prepared', $actors, $retail);
            $this->createAssessment->handle($cases['assessment_prepared']->permitApplication, $actors['assessment_officer']);

            $cases['treasury_lob_reopens'] = $this->readyCase($runId, 'treasury-lob-reopens', $actors, $retail);
            $staleAssessment = $this->createAssessment->handle($cases['treasury_lob_reopens']->permitApplication, $actors['assessment_officer']);
            $current = $cases['treasury_lob_reopens']->fresh()->currentVersion;
            $this->correctLinesOfBusiness->handle(
                $cases['treasury_lob_reopens'],
                [$retail->id, $restaurant->id],
                $actors['treasury'],
                'Synthetic UAT counter-check identified an additional Restaurant activity in the same permit application.',
                $current->sequence,
                $current->fingerprint,
                "{$runId}:treasury-lob-reopens",
            );
            $this->officeDetermination($cases['treasury_lob_reopens'], 'health', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 'Reopened after synthetic UAT LOB dependency change.');
            $staleAssessment->refresh();

            $cases['fresh_reassessment'] = $this->reassessedCase($runId, 'fresh-reassessment', $actors, $retail, $restaurant);

            $cases['treasurer_approved'] = $this->reassessedCase($runId, 'treasurer-approved', $actors, $retail, $restaurant);
            $approvedAssessment = $cases['treasurer_approved']->permitApplication->assessments()->whereNull('superseded_at')->sole();
            $this->recordAssessmentDecision->handle(
                $approvedAssessment,
                $actors['municipal_treasurer'],
                AssessmentDecisionAction::Approved,
                $this->assessmentFingerprint->hash($approvedAssessment),
            );

            $cases['returned_for_correction'] = $this->readyCase($runId, 'returned-for-correction', $actors, $retail);
            $returnedAssessment = $this->createAssessment->handle($cases['returned_for_correction']->permitApplication, $actors['assessment_officer']);
            $this->counterCheck->handle($returnedAssessment, $actors['treasury']);
            $this->recordAssessmentDecision->handle(
                $returnedAssessment,
                $actors['municipal_treasurer'],
                AssessmentDecisionAction::ReturnedForCorrection,
                $this->assessmentFingerprint->hash($returnedAssessment),
                'Synthetic UAT whole-Assessment return; targeted returns remain deferred.',
            );

            $cases['payment_locked'] = $this->reassessedCase($runId, 'payment-locked', $actors, $retail, $restaurant);
            $lockedAssessment = $cases['payment_locked']->permitApplication->assessments()->whereNull('superseded_at')->sole();
            $this->recordAssessmentDecision->handle(
                $lockedAssessment,
                $actors['municipal_treasurer'],
                AssessmentDecisionAction::Approved,
                $this->assessmentFingerprint->hash($lockedAssessment),
            );
            $this->createPaymentSchedule->handle($lockedAssessment, $actors['assessment_officer']);

            return $this->inventory($runId, collect($cases)->map->permitApplication);
        });
    }

    private function lineOfBusiness(string $code, string $name): LineOfBusiness
    {
        return LineOfBusiness::query()->firstOrCreate(['code' => $code], ['name' => $name, 'major_category' => 'Synthetic UAT', 'is_active' => true, 'metadata' => ['semantic_classification' => 'provisional_uat']]);
    }

    private function scenarioFeeRule(string $runId): FeeRule
    {
        FeeRule::query()
            ->where('code', 'like', 'EVAL-UAT-BASE-%')
            ->where('name', 'Evaluator UAT base proposal')
            ->where('scope', FeeRuleScope::Application->value)
            ->where('calculation_type', FeeRuleCalculationType::Fixed->value)
            ->where('basis', 'none')
            ->where('amount_cents', 10_000)
            ->whereDate('effective_from', '2099-01-01')
            ->whereDate('effective_until', '2099-12-31')
            ->where('metadata->semantic_classification', 'provisional_uat')
            ->where('metadata->production_liability', false)
            ->lockForUpdate()
            ->get()
            ->each(function (FeeRule $feeRule): void {
                if ($feeRule->is_active) {
                    $feeRule->update(['is_active' => false]);
                }
            });

        $feeRule = FeeRule::query()
            ->where('code', 'EVAL-UAT-BASE')
            ->whereDate('effective_from', '2099-01-01')
            ->lockForUpdate()
            ->first();

        if ($feeRule instanceof FeeRule
            && (data_get($feeRule->metadata, 'semantic_classification') !== 'provisional_uat'
                || data_get($feeRule->metadata, 'fixture_family') !== 'evaluator_uat_base')) {
            throw new RuntimeException('The stable Evaluator UAT FeeRule identity is occupied by a non-preview rule.');
        }

        $attributes = [
            'name' => 'Evaluator UAT base proposal',
            'category' => FeeRuleCategory::Fee,
            'scope' => FeeRuleScope::Application,
            'calculation_type' => FeeRuleCalculationType::Fixed,
            'basis' => 'none',
            'amount_cents' => 10_000,
            'effective_until' => '2099-12-31',
            'is_active' => true,
            'legal_basis' => null,
            'metadata' => [
                'semantic_classification' => 'provisional_uat',
                'fixture_family' => 'evaluator_uat_base',
                'latest_uat_run_id' => $runId,
                'production_liability' => false,
            ],
        ];

        if ($feeRule instanceof FeeRule) {
            $feeRule->update($attributes);

            return $feeRule;
        }

        return FeeRule::query()->create([
            'code' => 'EVAL-UAT-BASE',
            'effective_from' => '2099-01-01',
            ...$attributes,
        ]);
    }

    /** @param LineOfBusiness|array<int, LineOfBusiness> $linesOfBusiness */
    private function case(string $runId, string $key, User $citizen, LineOfBusiness|array $linesOfBusiness, User $creator): BusinessPermitEvaluation
    {
        $fixtureLabel = match ($key) {
            'interactive-golden' => 'Interactive Golden',
            'completed-assessment-conformance-golden' => 'Completed Assessment Conformance Golden',
            default => null,
        };
        $owner = BusinessOwner::query()->create(['name' => 'Synthetic Evaluator Owner '.str($key)->headline(), 'metadata' => ['uat_run_id' => $runId]]);
        $business = Business::query()->create(['business_owner_id' => $owner->id, 'name' => $fixtureLabel ?? 'Synthetic '.str($key)->headline().' Business', 'metadata' => ['uat_run_id' => $runId]]);
        $application = $this->statusMutation->createProvisionalUatFixture([
            'business_id' => $business->id,
            'submitted_by_id' => $citizen->id,
            'application_number' => 'EVAL-UAT-'.str(hash('sha256', $runId.'-'.$key))->substr(0, 10)->upper(),
            'tracking_reference' => 'EVAL-'.str(hash('sha256', $key.'-'.$runId))->substr(0, 12)->upper(),
            'type' => PermitApplicationType::Renewal,
            'status' => PermitApplicationStatus::Assessment,
            'application_year' => 2099,
            'submitted_at' => now(),
            'metadata' => ['business_permit_evaluation' => ['semantic_classification' => 'provisional_uat', 'uat_run_id' => $runId, 'case' => $key, 'fixture_label' => $fixtureLabel, 'production_liability' => false]],
        ]);
        collect(is_array($linesOfBusiness) ? $linesOfBusiness : [$linesOfBusiness])
            ->values()
            ->each(fn (LineOfBusiness $lineOfBusiness, int $index) => $application->lines()->create([
                'line_of_business_id' => $lineOfBusiness->id,
                'declared_gross_sales_cents' => 1_000_000 + ($index * 250_000),
                'capital_investment_cents' => 500_000 + ($index * 175_000),
                'quantity' => 1,
                'metadata' => ['semantic_classification' => 'provisional_uat'],
            ]));

        if (! $this->bploRoutingActor instanceof User) {
            throw new RuntimeException('Evaluator UAT requires an explicit BPLO routing actor.');
        }

        $this->recordBploRouting->handle(
            $application,
            $this->bploRoutingActor,
            'Synthetic UAT BPLO situational determination; this fixture does not commission production routing rules.',
            array_map(fn (string $office): array => [
                'office_code' => $office,
                'office_label' => str($office)->headline()->toString(),
                'situational_reason' => 'Selected by BPLO for this bounded synthetic UAT circumstance.',
                'required_work' => 'Determine applicable office work and any amount-bearing contribution.',
                'permit_application_line_id' => null,
            ], ['assessor', 'engineering', 'health', 'menro']),
        );

        return $this->initialize->handle($application, $creator);
    }

    /** @param array<string, User> $actors */
    private function readyCase(string $runId, string $key, array $actors, LineOfBusiness $retail): BusinessPermitEvaluation
    {
        return $this->case($runId, $key, $actors['citizen'], $retail, $actors['assessment_officer'])->fresh();
    }

    /** @param array<string, User> $actors */
    private function financialWorkingPaperCase(
        string $runId,
        string $key,
        array $actors,
        LineOfBusiness $retail,
        LineOfBusiness $repair,
        LineOfBusiness $restaurant,
    ): BusinessPermitEvaluation {
        $evaluation = $this->case($runId, $key, $actors['citizen'], [$retail, $repair], $actors['assessment_officer']);
        $applicationLines = $evaluation->permitApplication->lines->keyBy('line_of_business_id');
        $retailScope = $this->lineChargeMetadata($retail, $applicationLines->get($retail->id)?->id);
        $repairScope = $this->lineChargeMetadata($repair, $applicationLines->get($repair->id)?->id);

        $this->officeItem($evaluation, 'retail.business-tax', $actors['assessor'], 14_300, false, metadata: [...$retailScope, 'code' => 'UAT-RETAIL-BUSINESS-TAX', 'label' => 'Business Tax'], responsibleParty: 'assessor');
        $this->officeItem($evaluation, 'retail.mayors-permit', $actors['engineering'], 7_600, true, metadata: [...$retailScope, 'code' => 'UAT-RETAIL-MAYORS-PERMIT', 'label' => "Mayor's Permit Fee"], responsibleParty: 'engineering');
        $this->officeItem($evaluation, 'retail.weight-measure', $actors['assessor'], 3_400, false, metadata: [...$retailScope, 'code' => 'UAT-RETAIL-WEIGHT-MEASURE', 'label' => 'Weight & Measure'], responsibleParty: 'assessor');
        $this->officeItem($evaluation, 'repair.business-tax', $actors['assessor'], 16_800, false, metadata: [...$repairScope, 'code' => 'UAT-REPAIR-BUSINESS-TAX', 'label' => 'Business Tax'], responsibleParty: 'assessor');
        $this->officeItem($evaluation, 'repair.occupation-fee', $actors['engineering'], 6_900, true, metadata: [...$repairScope, 'code' => 'UAT-REPAIR-OCCUPATION', 'label' => 'Occupation Fee'], responsibleParty: 'engineering');
        $this->officeItem($evaluation, 'repair.solid-waste', $actors['menro'], 5_200, false, metadata: [...$repairScope, 'code' => 'UAT-REPAIR-SOLID-WASTE', 'label' => 'Solid Waste Management'], responsibleParty: 'menro');
        $this->officeItem($evaluation, 'repair.sanitary-permit', $actors['health'], 4_600, true, metadata: [...$repairScope, 'code' => 'UAT-REPAIR-SANITARY', 'label' => 'Sanitary Permit Fee'], responsibleParty: 'health');
        $this->officeItem(
            $evaluation,
            'restaurant.health-certificate',
            $actors['health'],
            8_700,
            true,
            BusinessPermitEvaluationApplicability::NotApplicable,
            [
                ...$this->lineChargeMetadata($restaurant),
                'fixture_dependency' => [
                    'semantic_classification' => 'provisional_uat',
                    'line_of_business_id' => $restaurant->id,
                ],
                'code' => 'UAT-RESTAURANT-HEALTH-CERT',
                'label' => 'Health Certificate',
            ],
            'health',
        );
        $this->officeItem(
            $evaluation,
            'restaurant.sanitary-permit',
            $actors['health'],
            6_100,
            true,
            BusinessPermitEvaluationApplicability::NotApplicable,
            [
                ...$this->lineChargeMetadata($restaurant),
                'fixture_dependency' => [
                    'semantic_classification' => 'provisional_uat',
                    'line_of_business_id' => $restaurant->id,
                ],
                'code' => 'UAT-RESTAURANT-SANITARY',
                'label' => 'Sanitary Permit Fee',
            ],
            'health',
        );

        return $evaluation->fresh();
    }

    /** @param array<string, User> $actors */
    private function completedAssessmentConformanceGolden(
        string $runId,
        array $actors,
        LineOfBusiness $retail,
        LineOfBusiness $repair,
        LineOfBusiness $restaurant,
    ): BusinessPermitEvaluation {
        $evaluation = $this->financialWorkingPaperCase(
            $runId,
            'completed-assessment-conformance-golden',
            $actors,
            $retail,
            $repair,
            $restaurant,
        );

        $this->completeWorkingPaperCharge($evaluation, 'retail.business-tax.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::Applicable, 14_300, 'Assessor confirmed the synthetic Retail Business Tax proposal.');
        $this->completeWorkingPaperCharge($evaluation, 'retail.mayors-permit.charge', $actors['engineering'], BusinessPermitEvaluationApplicability::Applicable, 8_200, 'Engineering corrected the synthetic Mayor permit proposal after inspection.');
        $this->completeWorkingPaperCharge($evaluation, 'retail.weight-measure.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::NotApplicable, 3_400, 'Weight and Measure is not applicable to this synthetic Retail activity.');
        $this->completeWorkingPaperCharge($evaluation, 'repair.business-tax.charge', $actors['assessor'], BusinessPermitEvaluationApplicability::Applicable, 16_800, 'Assessor confirmed the synthetic Repair Business Tax proposal.');
        $this->completeWorkingPaperCharge($evaluation, 'repair.occupation-fee.charge', $actors['engineering'], BusinessPermitEvaluationApplicability::Applicable, 6_900, 'Engineering confirmed the synthetic Occupation Fee proposal.');
        $this->completeWorkingPaperCharge($evaluation, 'repair.solid-waste.charge', $actors['menro'], BusinessPermitEvaluationApplicability::Applicable, 5_200, 'MENRO confirmed the synthetic Solid Waste proposal.');
        $this->completeWorkingPaperCharge($evaluation, 'repair.sanitary-permit.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 4_600, 'Health confirmed the synthetic Repair Sanitary Permit proposal.');

        $initialAssessment = $this->createAssessment->handle($evaluation->permitApplication->fresh(), $actors['assessment_officer']);
        $this->counterCheck->handle(
            $initialAssessment,
            $actors['treasury'],
            TreasuryCounterCheckResult::MaterialCorrection,
            'Treasury identified a material Line of Business correction against this prepared Assessment.',
        );
        $beforeCorrection = $evaluation->fresh()->currentVersion;
        $this->correctLinesOfBusiness->handle(
            $evaluation,
            [$retail->id, $repair->id, $restaurant->id],
            $actors['treasury'],
            'Treasury identified Restaurant in addition to the preserved Retail and Repair Services declaration.',
            $beforeCorrection->sequence,
            $beforeCorrection->fingerprint,
            "{$runId}:completed-assessment-conformance-golden:lob-correction",
        );

        $this->completeWorkingPaperCharge($evaluation, 'restaurant.health-certificate.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 8_700, 'Health completed the synthetic Restaurant Health Certificate review.');
        $this->completeWorkingPaperCharge($evaluation, 'restaurant.sanitary-permit.charge', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 6_100, 'Health completed the synthetic Restaurant Sanitary Permit review.');
        $assessment = $this->createAssessment->handle($evaluation->permitApplication->fresh(), $actors['assessment_officer']);
        $this->counterCheck->handle($assessment, $actors['treasury']);
        $this->recordAssessmentDecision->handle(
            $assessment,
            $actors['municipal_treasurer'],
            AssessmentDecisionAction::Approved,
            $this->assessmentFingerprint->hash($assessment),
        );

        if ($initialAssessment->refresh()->superseded_at === null || $assessment->total_amount_cents !== 115_800) {
            throw new RuntimeException('Completed Assessment Conformance Golden did not reproduce the accepted PHP 1,158.00 lifecycle.');
        }

        return $evaluation->fresh();
    }

    /** @return array<string, mixed> */
    private function lineChargeMetadata(LineOfBusiness $lineOfBusiness, ?int $permitApplicationLineId = null): array
    {
        return [
            'charge_scope' => 'line_of_business',
            'line_of_business_id' => $lineOfBusiness->id,
            'permit_application_line_id' => $permitApplicationLineId,
        ];
    }

    /** @param array<string, User> $actors */
    private function reassessedCase(string $runId, string $key, array $actors, LineOfBusiness $retail, LineOfBusiness $restaurant): BusinessPermitEvaluation
    {
        $evaluation = $this->readyCase($runId, $key, $actors, $retail);
        $oldAssessment = $this->createAssessment->handle($evaluation->permitApplication, $actors['assessment_officer']);
        $current = $evaluation->fresh()->currentVersion;
        $this->correctLinesOfBusiness->handle($evaluation, [$retail->id, $restaurant->id], $actors['treasury'], 'Synthetic UAT additional activity determination.', $current->sequence, $current->fingerprint, "{$runId}:{$key}:lob");
        $health = $this->officeDetermination($evaluation, 'health', $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 'Synthetic UAT dependency reopened Health responsibility.');
        $this->completeDetermination($health, $actors['health'], BusinessPermitEvaluationApplicability::Applicable, 'Synthetic UAT Health review complete.');
        $freshAssessment = $this->createAssessment->handle($evaluation->permitApplication->fresh(), $actors['assessment_officer']);
        $this->counterCheck->handle($freshAssessment, $actors['treasury']);

        if ($oldAssessment->refresh()->superseded_at === null) {
            throw new RuntimeException('Evaluator UAT reassessment failed to supersede the prior Assessment.');
        }

        return $evaluation->fresh();
    }

    /** @param array<string, mixed> $metadata */
    private function officeItem(
        BusinessPermitEvaluation $evaluation,
        string $office,
        User $actor,
        int $amount,
        bool $inspectionRequired,
        BusinessPermitEvaluationApplicability $applicability = BusinessPermitEvaluationApplicability::Applicable,
        array $metadata = [],
        ?string $responsibleParty = null,
    ): BusinessPermitEvaluationItem {
        $responsibleOffice = $responsibleParty ?? $office;
        $routingWork = $evaluation->permitApplication->bploRoutingDetermination?->works()
            ->where('office_code', $responsibleOffice)
            ->sole();

        return $this->defineItem->handle(
            $evaluation,
            "{$office}.charge",
            BusinessPermitEvaluationItemType::Charge,
            $responsibleOffice,
            true,
            true,
            $applicability,
            ['amount_cents' => $amount, 'inspection' => ['required' => $inspectionRequired, 'completed' => false]],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $actor,
            'Synthetic UAT system/default proposal for product operation only.',
            [
                'label' => str($office)->headline().' evaluation charge',
                'authorized_actor_id' => $actor->id,
                'inspection_required' => $inspectionRequired,
                'semantic_classification' => 'provisional_uat',
                'bplo_routing_work_id' => $routingWork?->id,
                ...$metadata,
            ],
        );
    }

    private function officeDetermination(BusinessPermitEvaluation $evaluation, string $office, User $actor, BusinessPermitEvaluationApplicability $applicability, ?string $reason = null): BusinessPermitEvaluationItem
    {
        return $this->defineItem->handle(
            $evaluation,
            "{$office}.determination",
            BusinessPermitEvaluationItemType::Determination,
            $office,
            true,
            true,
            $applicability,
            ['determination' => null, 'inspection' => ['required' => false, 'completed' => false]],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $actor,
            $reason ?? 'Synthetic UAT responsibility proposal.',
            ['label' => str($office)->headline().' applicability and clearance determination', 'authorized_actor_id' => $actor->id, 'inspection_required' => false],
        );
    }

    private function complete(BusinessPermitEvaluationItem $item, User $actor, int $amount, string $reason, bool $virtual): void
    {
        $version = $item->evaluation->fresh()->currentVersion;
        $this->completeResponsibility->handle(
            $item,
            $actor,
            BusinessPermitEvaluationApplicability::Applicable,
            ['amount_cents' => $amount, 'inspection' => ['required' => true, 'mode' => $virtual ? 'virtual' : 'physical', 'completed' => true, 'findings' => $reason]],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $reason,
            $version->sequence,
            $version->fingerprint,
            'uat-complete-'.$item->id.'-'.$amount,
        );
    }

    private function completeDetermination(BusinessPermitEvaluationItem $item, User $actor, BusinessPermitEvaluationApplicability $applicability, string $reason): void
    {
        $version = $item->evaluation->fresh()->currentVersion;
        $this->completeResponsibility->handle(
            $item,
            $actor,
            $applicability,
            ['determination' => $applicability->value, 'inspection' => ['required' => false, 'mode' => 'document_review', 'completed' => true, 'findings' => $reason]],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $reason,
            $version->sequence,
            $version->fingerprint,
            'uat-determine-'.$item->id.'-'.$applicability->value,
        );
    }

    private function completeWorkingPaperCharge(
        BusinessPermitEvaluation $evaluation,
        string $key,
        User $actor,
        BusinessPermitEvaluationApplicability $applicability,
        int $amount,
        string $reason,
    ): void {
        $item = $evaluation->items()->where('key', $key)->sole();
        $version = $evaluation->fresh()->currentVersion;
        $inspectionRequired = (bool) data_get($item->metadata, 'inspection_required');

        $this->completeResponsibility->handle(
            $item,
            $actor,
            $applicability,
            [
                'amount_cents' => $amount,
                'inspection' => [
                    'required' => $inspectionRequired,
                    'mode' => $inspectionRequired ? 'physical' : 'document_review',
                    'completed' => true,
                    'findings' => $reason,
                ],
            ],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $reason,
            $version->sequence,
            $version->fingerprint,
            'uat-completed-golden-'.$item->id.'-'.$applicability->value,
        );
    }

    /**
     * @param  iterable<int, PermitApplication>  $applications
     * @return array<string, mixed>
     */
    private function inventory(string $runId, iterable $applications): array
    {
        $previewBaseRules = FeeRule::query()
            ->where('code', 'like', 'EVAL-UAT-BASE%')
            ->where('metadata->semantic_classification', 'provisional_uat')
            ->get();

        return [
            'run_id' => $runId,
            'semantic_classification' => 'provisional_uat',
            'production_liability' => false,
            'pricing_fixture' => [
                'stable_code' => 'EVAL-UAT-BASE',
                'active_rule_count' => $previewBaseRules->where('is_active', true)->count(),
                'inactive_legacy_rule_count' => $previewBaseRules
                    ->where('is_active', false)
                    ->filter(fn (FeeRule $feeRule): bool => str_starts_with($feeRule->code, 'EVAL-UAT-BASE-'))
                    ->count(),
            ],
            'cases' => collect($applications)->mapWithKeys(function (PermitApplication $application): array {
                $evaluation = $application->businessPermitEvaluation;
                $assessment = $application->assessments()->whereNull('superseded_at')->first();

                return [data_get($application->metadata, 'business_permit_evaluation.case') => [
                    'permit_application_id' => $application->id,
                    'application_number' => $application->application_number,
                    'tracking_reference' => $application->tracking_reference,
                    'fixture_label' => data_get($application->metadata, 'business_permit_evaluation.fixture_label'),
                    'evaluation_id' => $evaluation?->id,
                    'evaluation_version' => $evaluation?->currentVersion?->sequence,
                    'assessment_id' => $assessment?->id,
                    'assessment_total_amount_cents' => $assessment?->total_amount_cents,
                    'url' => route('staff.permit-applications.evaluation.show', $application, false),
                ]];
            })->all(),
        ];
    }
}
