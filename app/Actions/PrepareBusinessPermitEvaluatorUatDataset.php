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
use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrepareBusinessPermitEvaluatorUatDataset
{
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
    ) {}

    /**
     * @param  array{citizen: User, assessment_officer: User, treasury: User, municipal_treasurer: User, engineering: User, health: User}  $actors
     * @return array<string, mixed>
     */
    public function handle(string $runId, array $actors): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Business Permit Evaluator UAT data is refused in production.');
        }

        $existing = PermitApplication::query()->where('metadata->business_permit_evaluation->uat_run_id', $runId)->get();
        if ($existing->isNotEmpty()) {
            return $this->inventory($runId, $existing);
        }

        return DB::transaction(function () use ($runId, $actors): array {
            $retail = $this->lineOfBusiness('EVAL-UAT-RETAIL', 'Retail — Evaluator UAT');
            $restaurant = $this->lineOfBusiness('EVAL-UAT-RESTAURANT', 'Restaurant — Evaluator UAT');
            $this->scenarioFeeRule($runId);

            $cases = [];
            $cases['just_created'] = $this->case($runId, 'just-created', $actors['citizen'], $retail, $actors['assessment_officer']);

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
        return FeeRule::query()->firstOrCreate(
            ['code' => 'EVAL-UAT-BASE-'.$runId],
            [
                'name' => 'Evaluator UAT base proposal',
                'category' => FeeRuleCategory::Fee,
                'scope' => FeeRuleScope::Application,
                'calculation_type' => FeeRuleCalculationType::Fixed,
                'basis' => 'none',
                'amount_cents' => 10_000,
                'effective_from' => '2099-01-01',
                'effective_until' => '2099-12-31',
                'is_active' => true,
                'legal_basis' => null,
                'metadata' => ['semantic_classification' => 'provisional_uat', 'uat_run_id' => $runId, 'production_liability' => false],
            ],
        );
    }

    private function case(string $runId, string $key, User $citizen, LineOfBusiness $lineOfBusiness, User $creator): BusinessPermitEvaluation
    {
        $owner = BusinessOwner::query()->create(['name' => 'Synthetic Evaluator Owner '.str($key)->headline(), 'metadata' => ['uat_run_id' => $runId]]);
        $business = Business::query()->create(['business_owner_id' => $owner->id, 'name' => 'Synthetic '.str($key)->headline().' Business', 'metadata' => ['uat_run_id' => $runId]]);
        $application = PermitApplication::query()->create([
            'business_id' => $business->id,
            'submitted_by_id' => $citizen->id,
            'application_number' => 'EVAL-UAT-'.str(hash('sha256', $runId.'-'.$key))->substr(0, 10)->upper(),
            'tracking_reference' => 'EVAL-'.str(hash('sha256', $key.'-'.$runId))->substr(0, 12)->upper(),
            'type' => PermitApplicationType::New,
            'status' => PermitApplicationStatus::Assessment,
            'application_year' => 2099,
            'submitted_at' => now(),
            'metadata' => ['business_permit_evaluation' => ['semantic_classification' => 'provisional_uat', 'uat_run_id' => $runId, 'case' => $key, 'production_liability' => false]],
        ]);
        $application->lines()->create([
            'line_of_business_id' => $lineOfBusiness->id,
            'declared_gross_sales_cents' => 1_000_000,
            'capital_investment_cents' => 500_000,
            'quantity' => 1,
            'metadata' => ['semantic_classification' => 'provisional_uat'],
        ]);

        return $this->initialize->handle($application, $creator);
    }

    /** @param array<string, User> $actors */
    private function readyCase(string $runId, string $key, array $actors, LineOfBusiness $retail): BusinessPermitEvaluation
    {
        $evaluation = $this->case($runId, $key, $actors['citizen'], $retail, $actors['assessment_officer']);
        $this->counterCheck->handle($evaluation, $actors['treasury']);

        return $evaluation->fresh();
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
        $this->counterCheck->handle($evaluation->fresh(), $actors['treasury']);
        $this->createAssessment->handle($evaluation->permitApplication->fresh(), $actors['assessment_officer']);

        if ($oldAssessment->refresh()->superseded_at === null) {
            throw new RuntimeException('Evaluator UAT reassessment failed to supersede the prior Assessment.');
        }

        return $evaluation->fresh();
    }

    private function officeItem(BusinessPermitEvaluation $evaluation, string $office, User $actor, int $amount, bool $inspectionRequired): BusinessPermitEvaluationItem
    {
        return $this->defineItem->handle(
            $evaluation,
            "{$office}.charge",
            BusinessPermitEvaluationItemType::Charge,
            $office,
            true,
            true,
            BusinessPermitEvaluationApplicability::Applicable,
            ['amount_cents' => $amount, 'inspection' => ['required' => $inspectionRequired, 'completed' => false]],
            BusinessPermitEvaluationSource::ProvisionalUat,
            $actor,
            'Synthetic UAT system/default proposal for product operation only.',
            ['label' => str($office)->headline().' evaluation charge', 'authorized_actor_id' => $actor->id, 'inspection_required' => $inspectionRequired],
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

    /**
     * @param  iterable<int, PermitApplication>  $applications
     * @return array<string, mixed>
     */
    private function inventory(string $runId, iterable $applications): array
    {
        return [
            'run_id' => $runId,
            'semantic_classification' => 'provisional_uat',
            'production_liability' => false,
            'cases' => collect($applications)->mapWithKeys(fn (PermitApplication $application): array => [
                data_get($application->metadata, 'business_permit_evaluation.case') => [
                    'permit_application_id' => $application->id,
                    'evaluation_id' => $application->businessPermitEvaluation?->id,
                    'url' => route('staff.permit-applications.evaluation.show', $application, false),
                ],
            ])->all(),
        ];
    }
}
