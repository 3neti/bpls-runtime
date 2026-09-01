<?php

namespace App\LifecycleScenarios;

use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\CreateRenewalPermitApplicationForExistingBusiness;
use App\Actions\DefineBusinessPermitEvaluationItem;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Actions\InspectBplsInstallation;
use App\Actions\RecordAssessmentDecision;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationItemType;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\FeeRuleExecutionStatus;
use App\Enums\FeeRuleScope;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\TreasuryCounterCheckResult;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\Business;
use App\Models\BusinessPermitEvaluation;
use App\Models\FeeRule;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RenewalHappyPathScenario
{
    public function __construct(
        private readonly RenewalHappyPathDefinition $definition,
        private readonly InspectBplsInstallation $inspectInstallation,
        private readonly CreatePermitApplication $createPermitApplication,
        private readonly CreateRenewalPermitApplicationForExistingBusiness $createRenewalForExistingBusiness,
        private readonly InitializeBusinessPermitEvaluation $initializeEvaluation,
        private readonly DefineBusinessPermitEvaluationItem $defineEvaluationItem,
        private readonly CompleteBusinessPermitEvaluationResponsibility $completeResponsibility,
        private readonly BusinessPermitEvaluationResolver $evaluationResolver,
        private readonly BusinessPermitEvaluationReadiness $evaluationReadiness,
        private readonly RecordBusinessPermitEvaluationCounterCheck $recordCounterCheck,
        private readonly CreateAssessmentForPermitApplication $createAssessment,
        private readonly AssessmentSnapshotFingerprint $assessmentFingerprint,
        private readonly RecordAssessmentDecision $recordAssessmentDecision,
        private readonly CreatePaymentScheduleForAssessment $createPaymentSchedule,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $installation = $this->inspectInstallation->handle();
        $this->assert($installation['integrity']['pass'], 'Scenario 02 requires a coherent bpls:install baseline.');

        $existing = $this->existingApplication();

        if ($existing instanceof PermitApplication) {
            $this->actors();

            return $this->checkpoint('Deterministic rerun found stale or nonconformant state', fn (): array => $this->result($existing));
        }

        return DB::transaction(function (): array {
            $actors = $this->actors();
            $linesOfBusiness = $this->linesOfBusiness();
            $this->assertAcceptedInspectionRule();
            $chronologyBusiness = $this->chronologyBusiness();

            $application = $this->checkpoint('Renewal was not lodged through canonical staff intake', function () use ($actors, $linesOfBusiness, $chronologyBusiness): PermitApplication {
                $applicationLines = [];
                foreach ($this->definition->linesOfBusiness() as $line) {
                    $applicationLines[] = [
                        'line_of_business_id' => $linesOfBusiness[$line['code']]->id,
                        'declared_gross_sales_cents' => $line['declared_gross_sales_cents'],
                        'capital_investment_cents' => $line['capital_investment_cents'],
                        'quantity' => 1,
                    ];
                }

                $application = $chronologyBusiness instanceof Business
                    ? $this->createRenewalForExistingBusiness->handle(
                        $chronologyBusiness,
                        RenewalHappyPathDefinition::ApplicationYear,
                        $applicationLines,
                        $actors['intake'],
                    )
                    : $this->createPermitApplication->handle([
                        'owner_name' => 'Scenario Synthetic Owner',
                        'owner_email' => null,
                        'owner_phone' => null,
                        'owner_address' => 'Synthetic Ipil product laboratory address',
                        'business_name' => 'Scenario Market and Kitchen',
                        'trade_name' => 'Scenario Product Laboratory',
                        'registration_number' => 'PRODUCT-LAB-MARKET-KITCHEN',
                        'business_address' => 'Synthetic Ipil product laboratory address',
                        'barangay' => 'Synthetic Barangay',
                        'application_number' => null,
                        'type' => PermitApplicationType::Renewal->value,
                        'application_year' => RenewalHappyPathDefinition::ApplicationYear,
                        'lines' => $applicationLines,
                    ], $actors['intake']);

                $metadata = $application->metadata ?? [];
                $metadata['lifecycle_scenario'] = [
                    'id' => RenewalHappyPathDefinition::Id,
                    'run_id' => RenewalHappyPathDefinition::RunId,
                    'definition_revision' => RenewalHappyPathDefinition::Revision,
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
                    'effective_date' => RenewalHappyPathDefinition::EffectiveDate,
                    'effective_time_source' => 'deterministic_scenario_clock',
                    'predecessor_permit_application_id' => $chronologyBusiness?->permitApplications()
                        ->where('type', PermitApplicationType::New)
                        ->where('application_year', NewApplicationHappyPathDefinition::ApplicationYear)
                        ->value('id'),
                ];
                $metadata['business_permit_evaluation'] = [
                    'semantic_classification' => 'provisional_uat',
                    'scenario_id' => RenewalHappyPathDefinition::Id,
                    'production_liability' => false,
                ];
                $application->forceFill(['metadata' => $metadata])->save();

                $this->assert($application->type === PermitApplicationType::Renewal, 'Canonical intake did not preserve Renewal type.');
                $this->assert($application->submitted_at !== null, 'Canonical staff intake did not record lodged/submitted time.');
                $this->assert($application->application_number === null, 'Scenario manufactured an unresolved official application number.');
                $this->assert($application->lines()->count() === 2, 'Renewal did not preserve exactly two declared LOBs.');

                if ($chronologyBusiness instanceof Business) {
                    $this->assert($application->business_id === $chronologyBusiness->id, 'Renewal did not reuse the Scenario 01 Business.');
                    $this->assert($chronologyBusiness->permitApplications()->count() === 2, 'Product-lab chronology does not contain exactly two applications for one Business.');
                }

                $this->linkCitizenPortalIdentity($actors['citizen'], $application);

                return $application;
            });

            $evaluation = $this->checkpoint('Evaluation did not initialize from the lodged Renewal', fn (): BusinessPermitEvaluation => $this->initializeEvaluation->handle($application, $actors['assessment_officer']));
            $routing = $this->applicationEvaluationRouting($application, $linesOfBusiness);

            $this->checkpoint('Required departmental responsibilities were not created', function () use ($evaluation, $actors, $linesOfBusiness, $routing): void {
                foreach ($this->definition->responsibilities() as $responsibility) {
                    $lineOfBusiness = $linesOfBusiness[$responsibility['line_of_business_code']];
                    $applicationLine = $evaluation->permitApplication->lines()->where('line_of_business_id', $lineOfBusiness->id)->sole();
                    $actor = $actors[$responsibility['department']];

                    $this->defineEvaluationItem->handle(
                        $evaluation,
                        $responsibility['key'],
                        BusinessPermitEvaluationItemType::Charge,
                        $responsibility['department'],
                        true,
                        true,
                        BusinessPermitEvaluationApplicability::Applicable,
                        [
                            'amount_cents' => $responsibility['amount_cents'],
                            'inspection' => [
                                'required' => $responsibility['inspection_required'],
                                'completed' => false,
                            ],
                        ],
                        BusinessPermitEvaluationSource::ProvisionalUat,
                        $actor,
                        $responsibility['provenance'],
                        [
                            'scenario_id' => RenewalHappyPathDefinition::Id,
                            'semantic_classification' => 'provisional_uat',
                            'production_liability' => false,
                            'authorized_actor_id' => $actor->id,
                            'charge_scope' => 'line_of_business',
                            'line_of_business_id' => $lineOfBusiness->id,
                            'permit_application_line_id' => $applicationLine->id,
                            'code' => $responsibility['code'],
                            'label' => $responsibility['label'],
                            'department_selection_reason' => $responsibility['reason'],
                            'inspection_required' => $responsibility['inspection_required'],
                        ],
                    );
                }

                $created = $this->scenarioResponsibilityProjection($evaluation->fresh());
                $this->assert(count($created) === 6, 'Expected six canonical charge responsibilities; found '.count($created).'.');
                $this->assert(collect($created)->every(fn (array $item): bool => $item['resolution'] === 'awaiting_responsible_confirmation'), 'A new responsibility was not waiting for its responsible department confirmation.');
                $this->assert(
                    $this->responsibilityKeys($created) === $this->routingKeys($routing),
                    'Application Evaluation routing and generated responsibilities do not reconcile exactly.',
                );
            });

            $this->expectFailure(
                'Assessment preparation before department completion was not safely refused',
                fn (): Assessment => $this->createAssessment->handle($application->fresh(), $actors['assessment_officer']),
                'Business Permit Evaluation is not Ready for Assessment',
            );
            $this->assert($application->assessments()->count() === 0, 'Premature Assessment refusal left a persisted Assessment.');

            $this->checkpoint('Canonical department work did not resolve every responsibility', function () use ($evaluation, $actors): void {
                foreach ($this->definition->responsibilities() as $responsibility) {
                    $item = $evaluation->items()->where('key', $responsibility['key'])->sole();
                    $version = $evaluation->fresh()->currentVersion;

                    $this->completeResponsibility->handle(
                        $item,
                        $actors[$responsibility['department']],
                        BusinessPermitEvaluationApplicability::Applicable,
                        [
                            'amount_cents' => $responsibility['amount_cents'],
                            'inspection' => [
                                'required' => $responsibility['inspection_required'],
                                'mode' => $responsibility['inspection_required'] ? 'physical' : 'document_review',
                                'completed' => true,
                                'findings' => $responsibility['reason'],
                            ],
                        ],
                        BusinessPermitEvaluationSource::ProvisionalUat,
                        $responsibility['reason'],
                        $version->sequence,
                        $version->fingerprint,
                        RenewalHappyPathDefinition::Id.':'.RenewalHappyPathDefinition::Revision.':'.$responsibility['key'].':complete',
                    );
                }

                $completed = $this->scenarioResponsibilityProjection($evaluation->fresh());
                $this->assert(collect($completed)->every(fn (array $item): bool => $item['resolution'] === 'resolved'), 'At least one department responsibility remained unresolved.');
                $this->assert(collect($completed)->every(fn (array $item): bool => $item['inspection_completed'] === true), 'At least one required inspection/review was not completed.');
            });

            $readyForAssessment = $this->evaluationReadiness->forAssessment($evaluation->fresh(), 'provisional_uat');
            $this->assert($readyForAssessment['ready'], 'Evaluation did not become Ready after all department work: '.implode(' ', $readyForAssessment['issues']));

            $this->checkpoint('Treasury counter-checker could approve the Assessment', function () use ($actors): void {
                $this->assert($actors['treasury']->cannot(UserPermission::ApproveAssessments->value), 'Treasury counter-checker unexpectedly has assessments.approve.');
                $this->assert($actors['municipal_treasurer']->can(UserPermission::ApproveAssessments->value), 'Municipal Treasurer lacks assessments.approve.');
            });

            $this->expectFailure(
                'Municipal Treasurer could arbitrarily mutate an office Evaluation responsibility',
                function () use ($evaluation, $actors): mixed {
                    $item = $evaluation->items()->where('key', 'retail.business-tax.charge')->sole();
                    $version = $evaluation->fresh()->currentVersion;

                    return $this->completeResponsibility->handle(
                        $item,
                        $actors['municipal_treasurer'],
                        BusinessPermitEvaluationApplicability::Applicable,
                        ['amount_cents' => 1, 'inspection' => ['required' => false, 'completed' => true]],
                        BusinessPermitEvaluationSource::ProvisionalUat,
                        'Unauthorized mutation attempt.',
                        $version->sequence,
                        $version->fingerprint,
                        RenewalHappyPathDefinition::Id.':'.RenewalHappyPathDefinition::Revision.':unauthorized-treasurer-mutation',
                    );
                },
                'belongs to',
            );

            $assessment = $this->checkpoint('Assessment Officer did not prepare the exact Evaluation snapshot', fn (): Assessment => $this->createAssessment->handle($application->fresh(), $actors['assessment_officer']));

            $this->expectFailure(
                'Assessment Officer could approve their own prepared Assessment',
                fn () => $this->recordAssessmentDecision->handle($assessment, $actors['assessment_officer'], AssessmentDecisionAction::Approved),
                'cannot record the Municipal Treasurer decision',
            );

            $this->expectFailure(
                'Municipal Treasurer could decide before Treasury counter-check of the prepared Assessment',
                fn () => $this->recordAssessmentDecision->handle($assessment, $actors['municipal_treasurer'], AssessmentDecisionAction::Approved),
                'requires Treasury counter-check of this exact snapshot',
            );

            $this->expectFailure(
                'Payment Schedule was created before exact Treasurer approval',
                fn (): PaymentSchedule => $this->createPaymentSchedule->handle($assessment, $actors['assessment_officer']),
                'approved by the Municipal Treasurer',
            );
            $this->assert($application->paymentSchedules()->count() === 0, 'Pre-approval Payment Schedule refusal left a persisted schedule.');

            $evaluationVersion = $evaluation->fresh()->currentVersion;
            $counterCheck = $this->checkpoint('Treasury counter-check did not bind the prepared Assessment and its source Evaluation version', fn () => $this->recordCounterCheck->handle(
                $assessment,
                $actors['treasury'],
                TreasuryCounterCheckResult::NoCorrection,
                'No correction: Treasury reconciled prepared Assessment #1 and its exact source Evaluation working paper.',
                $evaluationVersion->sequence,
                $evaluationVersion->fingerprint,
            ));
            $this->assert($counterCheck->assessment_id === $assessment->id, 'Treasury counter-check is not bound to prepared Assessment #1.');
            $this->assert($counterCheck->business_permit_evaluation_version_id === $assessment->business_permit_evaluation_version_id, 'Treasury counter-check is not bound to Assessment #1 source Evaluation version.');
            $this->assert($counterCheck->result === TreasuryCounterCheckResult::NoCorrection, 'Scenario 02 Treasury result is not no correction.');

            $assessmentHash = $this->assessmentFingerprint->hash($assessment);
            $decision = $this->checkpoint('Municipal Treasurer did not approve the exact Assessment snapshot', fn () => $this->recordAssessmentDecision->handle(
                $assessment,
                $actors['municipal_treasurer'],
                AssessmentDecisionAction::Approved,
                $assessmentHash,
            ));
            $this->assert($decision->assessment_snapshot_hash === $assessmentHash, 'Treasurer decision did not retain the exact Assessment fingerprint.');
            $this->assert($decision->total_amount_cents === $assessment->total_amount_cents, 'Treasurer decision total differs from the Assessment total.');

            $schedule = $this->checkpoint('Approved Assessment did not become Payable', fn (): PaymentSchedule => $this->createPaymentSchedule->handle($assessment->fresh(), $actors['assessment_officer']));
            $this->assert($schedule->status->value === 'pending', 'New Payment Schedule is not pending/payable.');

            $this->expectFailure(
                'Evaluation financial mutation did not lock after payment scheduling',
                function () use ($evaluation, $actors): mixed {
                    $item = $evaluation->items()->where('key', 'food.solid-waste.charge')->sole();
                    $version = $evaluation->fresh()->currentVersion;

                    return $this->completeResponsibility->handle(
                        $item,
                        $actors['menro'],
                        BusinessPermitEvaluationApplicability::Applicable,
                        ['amount_cents' => 7_100, 'inspection' => ['required' => true, 'completed' => true]],
                        BusinessPermitEvaluationSource::ProvisionalUat,
                        'Mutation after payment scheduling must fail.',
                        $version->sequence,
                        $version->fingerprint,
                        RenewalHappyPathDefinition::Id.':'.RenewalHappyPathDefinition::Revision.':post-schedule-mutation',
                    );
                },
                'cannot change after a Payment Schedule exists',
            );

            return $this->result($application->fresh());
        }, 3);
    }

    /** @return array<string, mixed> */
    private function result(PermitApplication $application): array
    {
        $definition = $this->definition->describe();
        $actors = $this->actors();
        $linesOfBusiness = $this->linesOfBusiness();
        $this->assertAcceptedInspectionRule();
        $application->load(['business.owner', 'lines.lineOfBusiness', 'businessPermitEvaluation']);
        $this->linkCitizenPortalIdentity($actors['citizen'], $application);
        $evaluation = $application->businessPermitEvaluation;
        $this->assert($evaluation instanceof BusinessPermitEvaluation, 'Persisted scenario application has no Evaluation.');
        $projection = $this->evaluationResolver->resolve($evaluation->fresh());
        $readiness = $this->evaluationReadiness->forAssessment($evaluation->fresh(), 'provisional_uat');
        $responsibilities = $this->scenarioResponsibilityProjection($evaluation->fresh());
        $assessment = $application->assessments()
            ->whereNull('superseded_at')
            ->with(['lines.lineOfBusiness', 'assessedBy', 'decision.decidedBy', 'treasuryCounterCheck.checkedBy'])
            ->sole();
        $schedule = $application->paymentSchedules()->with('lines')->sole();
        $decision = $assessment->decision;
        $counterCheck = $assessment->treasuryCounterCheck;
        $routing = $this->applicationEvaluationRouting($application, $linesOfBusiness);
        $predecessorApplicationId = data_get($application->metadata, 'lifecycle_scenario.predecessor_permit_application_id');
        $reusesProductLabIdentity = is_int($predecessorApplicationId);

        if ($reusesProductLabIdentity) {
            $predecessor = PermitApplication::query()->find($predecessorApplicationId);
            $this->assert($predecessor?->business_id === $application->business_id, 'Renewal predecessor does not share the same Business.');
            $this->assert($predecessor?->type === PermitApplicationType::New, 'Renewal predecessor is not a New Business Permit application.');
            $this->assert($predecessor?->application_year === NewApplicationHappyPathDefinition::ApplicationYear, 'Renewal predecessor is not the 2025 application.');
        }

        $this->assert($application->status === PermitApplicationStatus::PendingPayment, 'Application is not in the payable pending_payment state.');
        $this->assert($readiness['ready'], 'Persisted Evaluation is not Ready: '.implode(' ', $readiness['issues']));
        $this->assert(count($responsibilities) === $definition['expected']['responsibility_count'], 'Responsibility count changed on rerun.');
        $this->assert(collect($responsibilities)->where('resolution', 'resolved')->count() === 6, 'Resolved responsibility count changed on rerun.');
        $this->assert($projection['total_amount_cents'] === RenewalHappyPathDefinition::ExpectedGrandTotalCents, 'Evaluation Grand Total does not equal PHP 1,220.00.');
        $this->assert($assessment->total_amount_cents === $projection['total_amount_cents'], 'Evaluation total does not equal Assessment total.');
        $this->assert($assessment->lines->count() === $definition['expected']['assessment_line_count'], 'Assessment does not contain exactly seven financial items.');
        $this->assert($assessment->lines->sum('amount_cents') === $assessment->total_amount_cents, 'Assessment lines do not reconcile to Assessment total.');
        $this->assert($assessment->lines->pluck('business_permit_evaluation_item_id')->filter()->unique()->count() === 6, 'Each resolved applicable Evaluation charge did not enter Assessment exactly once.');
        $this->assert($assessment->lines->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->count() === 1, 'Accepted Business Inspection Fee is absent or duplicated.');
        $this->assert($assessment->business_permit_evaluation_version_id === $projection['version_id'], 'Assessment is not bound to the exact Evaluation version.');
        $this->assert($assessment->business_permit_evaluation_fingerprint === $projection['current_fingerprint'], 'Assessment is not bound to the exact Evaluation fingerprint.');
        $this->assert($counterCheck?->assessment_id === $assessment->id, 'Treasury counter-check is not bound to the current prepared Assessment.');
        $this->assert($counterCheck?->assessment_snapshot_hash === $this->assessmentFingerprint->hash($assessment), 'Treasury counter-check no longer matches the prepared Assessment fingerprint.');
        $this->assert($counterCheck?->result === TreasuryCounterCheckResult::NoCorrection, 'Treasury counter-check did not preserve the no-correction result.');
        $this->assert(
            $this->responsibilityKeys($responsibilities) === $this->routingKeys($routing),
            'Application Evaluation routing and responsibilities changed after certification.',
        );
        $this->assert($assessment->assessed_by_id !== $decision?->decided_by_id, 'Assessment preparer and Municipal Treasurer are not distinct.');
        $this->assert($decision?->assessment_snapshot_hash === $this->assessmentFingerprint->hash($assessment), 'Treasurer approval no longer matches the Assessment fingerprint.');
        $this->assert($schedule->total_amount_cents === $assessment->total_amount_cents, 'Payment Schedule total does not equal Assessment total.');
        $this->assert($schedule->lines->count() === $assessment->lines->count(), 'Payment Schedule does not preserve each Assessment line exactly once.');

        $lineSections = collect($this->lineSections($projection));
        foreach ($this->definition->linesOfBusiness() as $expectedLine) {
            $section = $lineSections->firstWhere('line_of_business_name', $expectedLine['name']);
            $this->assert(is_array($section), "Financial working paper omitted LOB [{$expectedLine['name']}].");
            $this->assert($section['subtotal_amount_cents'] === $expectedLine['subtotal_amount_cents'], "LOB subtotal changed for [{$expectedLine['name']}].");
        }
        $this->assert(data_get($projection, 'financial_working_paper.application_subtotal_amount_cents') === 35_000, 'Application-wide subtotal does not equal PHP 350.00.');
        $this->assert(data_get($projection, 'financial_working_paper.grand_total_amount_cents') === RenewalHappyPathDefinition::ExpectedGrandTotalCents, 'Working-paper Grand Total is unavailable or incorrect.');

        $timeline = $this->timeline();
        $semanticHash = hash('sha256', json_encode([
            'scenario_id' => RenewalHappyPathDefinition::Id,
            'application_type' => $application->type->value,
            'effective_date' => RenewalHappyPathDefinition::EffectiveDate,
            'lob_codes' => $application->lines->pluck('lineOfBusiness.code')->sort()->values()->all(),
            'responsibility_keys' => collect($responsibilities)->pluck('key')->sort()->values()->all(),
            'assessment_lines' => $assessment->lines->map(fn ($line): array => ['code' => $line->code, 'amount_cents' => $line->amount_cents])->sortBy('code')->values()->all(),
            'grand_total_amount_cents' => $assessment->total_amount_cents,
            'terminal_status' => $application->status->value,
            'timeline' => collect($timeline)->pluck('milestone')->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'schema_version' => 'bpls.lifecycle-certification.v1',
            'scenario_id' => RenewalHappyPathDefinition::Id,
            'scenario_revision' => RenewalHappyPathDefinition::Revision,
            'status' => 'passed',
            'business_question' => RenewalHappyPathDefinition::EvidenceQuestion,
            'evidence' => $definition['evidence'],
            'first_failure' => null,
            'semantic_result_hash' => $semanticHash,
            'database_driver' => DB::connection()->getDriverName(),
            'scenario_time' => [
                'effective_date' => RenewalHappyPathDefinition::EffectiveDate,
                'application_year' => RenewalHappyPathDefinition::ApplicationYear,
                'execution_timestamps_are_actual' => true,
            ],
            'system_bootstrap' => $this->bootstrapInventory($actors, $linesOfBusiness),
            'onboarding' => [
                'canonical_action' => $reusesProductLabIdentity
                    ? 'CreateRenewalPermitApplicationForExistingBusiness'
                    : 'CreatePermitApplication',
                'disposition' => $reusesProductLabIdentity
                    ? 'Canonical Renewal intake reuses the persisted Scenario 01 BusinessOwner and Business without mutating registry identity. submitted_by_id remains the BPLO intake audit actor.'
                    : 'Isolated certification creates a disposable synthetic BusinessOwner and Business with the Renewal. submitted_by_id remains the BPLO intake audit actor.',
                'chronology' => [
                    'reuses_scenario_01_identity' => $reusesProductLabIdentity,
                    'predecessor_permit_application_id' => $predecessorApplicationId,
                    'prior_payment_or_release_invented' => false,
                ],
                'portal_identity' => [
                    'id' => $actors['citizen']->id,
                    'name' => $actors['citizen']->name,
                    'business_owner_id' => $actors['citizen']->business_owner_id,
                    'application_submitter_id' => $application->submitted_by_id,
                    'visible_via' => 'user.business_owner_id -> businesses.business_owner_id -> permit_applications.business_id',
                    'synthetic' => true,
                ],
                'owner_customer' => [
                    'id' => $application->business->owner->id,
                    'name' => $application->business->owner->name,
                    'synthetic' => true,
                ],
                'business' => [
                    'id' => $application->business->id,
                    'name' => $application->business->name,
                    'business_owner_id' => $application->business->business_owner_id,
                    'synthetic' => true,
                ],
            ],
            'application' => [
                'id' => $application->id,
                'type' => $application->type->value,
                'status' => $application->status->value,
                'application_year' => $application->application_year,
                'application_number' => $application->application_number,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'business' => [
                    'id' => $application->business->id,
                    'name' => $application->business->name,
                    'owner_name' => $application->business->owner->name,
                    'synthetic' => true,
                ],
            ],
            'lines_of_business' => $lineSections->map(fn (array $section): array => [
                'id' => $section['line_of_business_id'],
                'name' => $section['line_of_business_name'],
                'charges' => collect($this->sectionCharges($section))->map(fn (array $charge): array => [
                    'code' => $charge['code'],
                    'label' => $charge['label'],
                    'responsible_party' => $charge['responsible_party'],
                    'amount_cents' => $charge['resolved_amount_cents'],
                    'source_classification' => $charge['source_classification'],
                ])->values()->all(),
                'subtotal_amount_cents' => $section['subtotal_amount_cents'],
            ])->values()->all(),
            'application_evaluation_routing' => $routing,
            'responsibilities' => [
                'created_count' => count($responsibilities),
                'resolved_count' => collect($responsibilities)->where('resolution', 'resolved')->count(),
                'items' => $responsibilities,
            ],
            'evaluation' => [
                'id' => $projection['evaluation_id'],
                'version_id' => $projection['version_id'],
                'version_sequence' => $projection['version_sequence'],
                'fingerprint' => $projection['current_fingerprint'],
                'fingerprint_current' => $projection['fingerprint_current'],
                'readiness' => $readiness['ready'] ? 'ready' : 'not_ready',
                'charges' => $this->evaluationCharges($projection),
                'subtotals' => [
                    'line_of_business' => $lineSections->mapWithKeys(fn (array $section): array => [$section['line_of_business_name'] => $section['subtotal_amount_cents']])->all(),
                    'application_wide_amount_cents' => $projection['financial_working_paper']['application_subtotal_amount_cents'],
                ],
                'grand_total_amount_cents' => $projection['financial_working_paper']['grand_total_amount_cents'],
            ],
            'assessment' => [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'evaluation_version_id' => $assessment->business_permit_evaluation_version_id,
                'evaluation_fingerprint' => $assessment->business_permit_evaluation_fingerprint,
                'assessment_fingerprint' => $this->assessmentFingerprint->hash($assessment),
                'total_amount_cents' => $assessment->total_amount_cents,
                'prepared_by' => ['id' => $assessment->assessedBy?->id, 'name' => $assessment->assessedBy?->name],
                'line_count' => $assessment->lines->count(),
            ],
            'treasury_counter_check' => [
                'id' => $counterCheck?->id,
                'status' => 'completed',
                'result' => $counterCheck?->result?->value,
                'assessment_id' => $counterCheck?->assessment_id,
                'assessment_snapshot_hash' => $counterCheck?->assessment_snapshot_hash,
                'evaluation_version_id' => $projection['version_id'],
                'evaluation_version_sequence' => $projection['version_sequence'],
                'evaluation_fingerprint' => $projection['current_fingerprint'],
                'checked_by' => ['id' => $counterCheck?->checkedBy?->id, 'name' => $counterCheck?->checkedBy?->name],
                'checked_at' => $counterCheck?->checked_at->toIso8601String(),
                'note' => $counterCheck?->reason,
            ],
            'treasurer_decision' => [
                'action' => $decision?->action->value,
                'assessment_id' => $assessment->id,
                'assessment_snapshot_hash' => $decision?->assessment_snapshot_hash,
                'total_amount_cents' => $decision?->total_amount_cents,
                'decided_by' => ['id' => $decision?->decidedBy?->id, 'name' => $decision?->decidedBy?->name],
            ],
            'payment_schedule' => [
                'id' => $schedule->id,
                'status' => $schedule->status->value,
                'payment_mode' => $schedule->payment_mode,
                'total_amount_cents' => $schedule->total_amount_cents,
                'paid_amount_cents' => $schedule->paid_amount_cents,
                'line_count' => $schedule->lines->count(),
            ],
            'payable' => [
                'status' => 'payable',
                'source' => 'pending_payment_schedule',
                'amount_cents' => $schedule->total_amount_cents - $schedule->paid_amount_cents,
                'externally_settled' => false,
            ],
            'isolation_inventory' => [
                'scenario_applications' => PermitApplication::query()
                    ->where('metadata->lifecycle_scenario->id', RenewalHappyPathDefinition::Id)
                    ->where('metadata->lifecycle_scenario->definition_revision', RenewalHappyPathDefinition::Revision)
                    ->count(),
                'scenario_businesses' => $application->business()->where('registration_number', 'PRODUCT-LAB-MARKET-KITCHEN')->count(),
                'scenario_responsibilities' => count($responsibilities),
                'scenario_evaluation_charges' => collect($this->evaluationCharges($projection))->count(),
                'current_assessments' => $application->assessments()->whereNull('superseded_at')->count(),
                'assessment_lines' => $assessment->lines->count(),
                'treasury_counter_checks' => $application->businessPermitEvaluation?->versions()->whereHas('counterCheck')->count(),
                'payment_schedules' => $application->paymentSchedules()->count(),
                'accepted_inspection_fee_rules' => FeeRule::query()->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->count(),
                'expected_nonaccumulating' => true,
            ],
            'negative_assertions' => $this->stableNegativeAssertions(),
            'milestones' => collect($timeline)->pluck('milestone')->all(),
            'timeline' => $timeline,
            'action_trace' => $this->actionTrace(),
            'artifacts' => [
                'root' => 'lifecycle-scenarios/'.RenewalHappyPathDefinition::Id.'/'.RenewalHappyPathDefinition::RunId,
                'json' => 'result.json',
                'action_trace' => 'action-trace.json',
            ],
        ];
    }

    private function existingApplication(): ?PermitApplication
    {
        $specimen = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', RenewalHappyPathDefinition::Id)
            ->where('scenario_revision', RenewalHappyPathDefinition::Revision)
            ->with('permitApplication')
            ->first();
        $applications = PermitApplication::query()
            ->where('metadata->lifecycle_scenario->id', RenewalHappyPathDefinition::Id)
            ->where('metadata->lifecycle_scenario->definition_revision', RenewalHappyPathDefinition::Revision)
            ->get();

        if ($specimen instanceof LifecycleScenarioSpecimen) {
            if ($applications->count() !== 1 || $applications->first()?->id !== $specimen->permit_application_id) {
                throw new RenewalHappyPathFailure('Harness-owned persisted specimen is singular', 'Scenario 02 ownership manifest does not match exactly one application.');
            }

            return $specimen->permitApplication;
        }

        if ($applications->isNotEmpty()) {
            throw new RenewalHappyPathFailure('Scenario persistence is harness-owned', 'Unowned Scenario 02 transactional residue exists; refusing name or metadata inference.');
        }

        return null;
    }

    private function chronologyBusiness(): ?Business
    {
        $newApplicationSpecimen = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', NewApplicationHappyPathDefinition::Id)
            ->where('scenario_revision', NewApplicationHappyPathDefinition::Revision)
            ->with('permitApplication.business')
            ->first();

        if (! $newApplicationSpecimen instanceof LifecycleScenarioSpecimen) {
            return null;
        }

        $newApplication = $newApplicationSpecimen->permitApplication;
        $this->assert($newApplication->type === PermitApplicationType::New, 'Scenario 01 predecessor is not a New Business Permit application.');
        $this->assert($newApplication->application_year === NewApplicationHappyPathDefinition::ApplicationYear, 'Scenario 01 predecessor is not effective for 2025.');
        $this->assert($newApplication->business instanceof Business, 'Scenario 01 predecessor has no Business.');

        return $newApplication->business;
    }

    /** @return array<string, User> */
    private function actors(): array
    {
        return [
            'citizen' => $this->actor('citizen', 'Scenario Citizen', [UserPermission::AccessCitizen, UserPermission::CreateOwnPermitApplications, UserPermission::EditOwnPermitApplications, UserPermission::SubmitOwnPermitApplications, UserPermission::UploadOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplications, UserPermission::ViewOwnPermitApplicationDocuments, UserPermission::ViewOwnPermitApplicationFinancials, UserPermission::ViewOwnBusinessPermitEvaluations]),
            'intake' => $this->actor('intake', 'Scenario 02 BPLO Intake Officer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::CreatePermitApplications]),
            'assessment_officer' => $this->actor('assessment-officer', 'Scenario 02 Assessment Officer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::AssessPermitApplications, UserPermission::ViewPaymentSchedules, UserPermission::PreparePaymentSchedules]),
            'assessor' => $this->actor('assessor', 'Scenario 02 Assessor', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]),
            'engineering' => $this->actor('engineering', 'Scenario 02 Engineering Officer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]),
            'health' => $this->actor('health', 'Scenario 02 Health Officer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]),
            'menro' => $this->actor('menro', 'Scenario 02 MENRO Officer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ContributeBusinessPermitEvaluations]),
            'treasury' => $this->actor('treasury-counter-check', 'Scenario 02 Treasury Counter-checker', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::CounterCheckBusinessPermitEvaluations, UserPermission::CorrectEvaluationLinesOfBusiness]),
            'municipal_treasurer' => $this->actor('municipal-treasurer', 'Scenario 02 Municipal Treasurer', [UserPermission::AccessStaff, UserPermission::ViewPermitApplications, UserPermission::ViewBusinessPermitEvaluations, UserPermission::ApproveAssessments]),
        ];
    }

    private function linkCitizenPortalIdentity(User $citizen, PermitApplication $application): void
    {
        $businessOwnerId = $application->business->business_owner_id;
        $this->assert(
            $citizen->business_owner_id === null || $citizen->business_owner_id === $businessOwnerId,
            'Deterministic Scenario Citizen identity is already linked to another BusinessOwner.',
        );

        if ($citizen->business_owner_id === null) {
            $citizen->forceFill(['business_owner_id' => $businessOwnerId])->save();
        }

        $citizen->refresh();
        $this->assert($citizen->business_owner_id === $businessOwnerId, 'Scenario 02 Citizen portal identity is not linked to the application BusinessOwner.');
        $this->assert($application->submitted_by_id !== $citizen->id, 'Staff-lodged Scenario 02 incorrectly records the Citizen as its submission actor.');
    }

    /** @param list<UserPermission> $permissions */
    private function actor(string $key, string $name, array $permissions): User
    {
        $actorPrefix = $key === 'citizen' ? 'scenario-01' : 'scenario-02';
        $role = Role::query()->firstOrCreate(
            ['code' => $actorPrefix.'-'.$key],
            ['name' => $name, 'description' => 'Synthetic product-lab role; not a production municipal assignment.'],
        );
        $permissionIds = collect($permissions)->map(function (UserPermission $permission): int {
            return Permission::query()->firstOrCreate(
                ['code' => $permission->value],
                ['name' => str($permission->value)->replace('.', ' ')->title()->toString()],
            )->id;
        });
        $role->permissions()->sync($permissionIds);

        $user = User::query()->firstOrCreate(
            ['email' => ($key === 'citizen' ? 'scenario-citizen' : 'scenario-02-'.$key).'@example.test'],
            [
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make('scenario-02-not-a-login-credential'),
                'email_verified_at' => now(),
            ],
        );
        $this->assert($user->role_id === $role->id, "Synthetic actor identity [{$key}] is occupied by another role.");

        return $user->load('role.permissions');
    }

    /** @return array<string, LineOfBusiness> */
    private function linesOfBusiness(): array
    {
        return collect($this->definition->linesOfBusiness())->mapWithKeys(function (array $definition): array {
            $lineOfBusiness = LineOfBusiness::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'major_category' => $definition['major_category'],
                    'is_active' => true,
                    'metadata' => [
                        'scenario_id' => 'product-lab-chronology',
                        'semantic_classification' => 'provisional_uat',
                        'production_liability' => false,
                    ],
                ],
            );
            $this->assert(data_get($lineOfBusiness->metadata, 'scenario_id') === 'product-lab-chronology', "LOB code [{$definition['code']}] is occupied by non-scenario data.");

            return [$definition['code'] => $lineOfBusiness];
        })->all();
    }

    private function assertAcceptedInspectionRule(): void
    {
        $feeRule = FeeRule::query()->with('currentReconciliation')->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->sole();
        $this->assert($feeRule->scope === FeeRuleScope::Application, 'Accepted Business Inspection Fee is not application-wide.');
        $this->assert($feeRule->amount_cents === 35_000, 'Accepted Business Inspection Fee is not PHP 350.00.');
        $this->assert($feeRule->currentReconciliation?->execution_status === FeeRuleExecutionStatus::Executable, 'Accepted Business Inspection Fee is not executable.');
    }

    /** @return list<array<string, mixed>> */
    private function scenarioResponsibilityProjection(BusinessPermitEvaluation $evaluation): array
    {
        return array_values(collect($this->evaluationResolver->resolve($evaluation)['items'])
            ->filter(fn (array $item): bool => data_get($item, 'metadata.scenario_id') === RenewalHappyPathDefinition::Id)
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'key' => $item['key'],
                'department' => $item['responsible_party'],
                'department_selection_reason' => data_get($item, 'metadata.department_selection_reason'),
                'line_of_business_id' => data_get($item, 'metadata.line_of_business_id'),
                'code' => data_get($item, 'metadata.code'),
                'label' => data_get($item, 'metadata.label'),
                'applicability' => $item['applicability'],
                'proposal_amount_cents' => data_get($item, 'default_value.amount_cents'),
                'resolved_amount_cents' => data_get($item, 'value.amount_cents'),
                'inspection_required' => data_get($item, 'metadata.inspection_required'),
                'inspection_completed' => data_get($item, 'value.inspection.completed'),
                'resolution' => $item['resolution'],
                'source_classification' => $item['source_classification'],
                'action' => $item['action'],
                'actor_id' => $item['actor_id'],
            ])
            ->sortBy('key')
            ->values()
            ->all());
    }

    /**
     * @param  array<string, User>  $actors
     * @param  array<string, LineOfBusiness>  $linesOfBusiness
     * @return array<string, mixed>
     */
    private function bootstrapInventory(array $actors, array $linesOfBusiness): array
    {
        $inspectionRule = FeeRule::query()->with('currentReconciliation')->where('code', 'MRC-3A-04-BUSINESS-INSPECTION')->sole();

        return [
            'schema' => [
                'migrations_repository_present' => Schema::hasTable('migrations'),
                'required_operational_tables_present' => collect([
                    'users',
                    'business_owners',
                    'businesses',
                    'permit_applications',
                    'permit_application_lines',
                    'business_permit_evaluations',
                    'assessments',
                    'business_permit_evaluation_counter_checks',
                    'payment_schedules',
                ])->every(fn (string $table): bool => Schema::hasTable($table)),
            ],
            'municipal_runtime_configuration' => [
                'municipality' => config('municipality.name'),
                'province' => config('municipality.province'),
                'system_name' => config('municipality.system_name'),
                'source' => 'config/municipality.php',
            ],
            'actor_capabilities' => collect($actors)->map(fn (User $actor): array => [
                'user_id' => $actor->id,
                'role_code' => $actor->role?->code,
                'permissions' => $actor->role?->permissions->pluck('code')->sort()->values()->all() ?? [],
                'classification' => 'synthetic_scenario_actor',
            ])->all(),
            'reference_data' => [
                'scenario_line_of_business_codes' => collect($linesOfBusiness)->pluck('code')->sort()->values()->all(),
                'classification' => 'provisional_uat_routing_reference',
            ],
            'accepted_business_inspection_fee' => [
                'fee_rule_id' => $inspectionRule->id,
                'code' => $inspectionRule->code,
                'amount_cents' => $inspectionRule->amount_cents,
                'scope' => $inspectionRule->scope->value,
                'classification' => 'accepted_governed_municipal_rule',
                'provisional_uat' => false,
                'execution_status' => $inspectionRule->currentReconciliation?->execution_status->value,
            ],
        ];
    }

    /**
     * @param  array<string, LineOfBusiness>  $linesOfBusiness
     * @return array<string, mixed>
     */
    private function applicationEvaluationRouting(PermitApplication $application, array $linesOfBusiness): array
    {
        $declaredLineIds = $application->lines()->pluck('line_of_business_id')->map(fn (mixed $id): int => (int) $id);
        $requiredWork = collect($this->definition->responsibilities())->map(function (array $responsibility) use ($declaredLineIds, $linesOfBusiness): array {
            $lineOfBusiness = $linesOfBusiness[$responsibility['line_of_business_code']];
            $this->assert($declaredLineIds->contains($lineOfBusiness->id), "Routing requires an undeclared LOB [{$lineOfBusiness->code}].");

            return [
                'key' => $responsibility['key'],
                'line_of_business_id' => $lineOfBusiness->id,
                'line_of_business_code' => $lineOfBusiness->code,
                'line_of_business_name' => $lineOfBusiness->name,
                'department' => $responsibility['department'],
                'work_label' => $responsibility['label'],
                'reason' => $responsibility['reason'],
                'inspection_or_review_required' => $responsibility['inspection_required'],
                'classification' => 'provisional_uat',
            ];
        })->sortBy('key')->values();

        return [
            'canonical_noun' => 'Business Permit Evaluation required-work routing',
            'disposition' => 'projected',
            'persisted_aggregate_created' => false,
            'source_facts' => ['lodged Renewal', 'declared Lines of Business', 'Scenario 02 provisional UAT applicability', 'generated Evaluation responsibilities'],
            'classification' => 'provisional_uat',
            'groups' => $requiredWork
                ->groupBy('line_of_business_id')
                ->map(fn ($items): array => [
                    'line_of_business_id' => $items->first()['line_of_business_id'],
                    'line_of_business_code' => $items->first()['line_of_business_code'],
                    'line_of_business_name' => $items->first()['line_of_business_name'],
                    'required_work' => $items->map(fn (array $item): array => [
                        'key' => $item['key'],
                        'department' => $item['department'],
                        'work_label' => $item['work_label'],
                        'reason' => $item['reason'],
                        'inspection_or_review_required' => $item['inspection_or_review_required'],
                    ])->values()->all(),
                ])->values()->all(),
            'required_work_count' => $requiredWork->count(),
            'required_work' => $requiredWork->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $responsibilities
     * @return list<string>
     */
    private function responsibilityKeys(array $responsibilities): array
    {
        $keys = [];

        foreach ($responsibilities as $responsibility) {
            $key = $responsibility['key'] ?? null;
            if (! is_string($key)) {
                throw new RenewalHappyPathFailure('Responsibility identity is present', 'A projected responsibility has no canonical key.');
            }

            $keys[] = $key;
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $routing
     * @return list<string>
     */
    private function routingKeys(array $routing): array
    {
        $requiredWork = $routing['required_work'] ?? null;
        if (! is_array($requiredWork)) {
            throw new RenewalHappyPathFailure('Routing required work is present', 'The routing projection returned no required-work list.');
        }

        $keys = [];
        foreach ($requiredWork as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : null;
            if (! is_string($key)) {
                throw new RenewalHappyPathFailure('Routing identity is present', 'A routing requirement has no canonical key.');
            }

            $keys[] = $key;
        }

        sort($keys);

        return $keys;
    }

    /** @return list<array<string, mixed>> */
    private function timeline(): array
    {
        $steps = [
            ['system_bootstrapped', 'System', 'Canonical migrations + RevenueCodeFeeCatalogSeeder + scenario actor/reference preparation', 'Required schema, actor capabilities, reference data, and the governed Business Inspection Fee are available.'],
            ['owner_onboarded', 'BPLO Intake Officer', 'CreatePermitApplication', 'Canonical staff intake creates the synthetic BusinessOwner as part of one atomic intake action.'],
            ['business_onboarded', 'BPLO Intake Officer', 'CreatePermitApplication', 'The same canonical intake action creates the synthetic Business owned by that BusinessOwner.'],
            ['renewal_lodged', 'BPLO Intake Officer', 'CreatePermitApplication', 'Renewal is lodged without manufacturing an official application number.'],
            ['lines_of_business_declared', 'BPLO Intake Officer', 'CreatePermitApplication', 'Two LOB declarations are persisted with the lodged Renewal.'],
            ['evaluation_initialized', 'Assessment Officer', 'InitializeBusinessPermitEvaluation', 'Applicant LOB facts enter versioned Evaluation.'],
            ['application_evaluation_routing_projected', 'System', 'Business Permit Evaluation read projection', 'Provisional UAT applicability compiles the required municipal work by LOB and reason without a second persisted aggregate.'],
            ['responsibilities_created', 'Assessment Officer', 'DefineBusinessPermitEvaluationItem', 'Six required department charge responsibilities are created.'],
            ['premature_assessment_refused', 'Assessment Officer', 'CreateAssessmentForPermitApplication', 'Readiness guard refuses incomplete department work.'],
            ['departments_completed', 'Concerned Offices', 'CompleteBusinessPermitEvaluationResponsibility', 'Six confirmations resolve applicability, review, and amounts.'],
            ['evaluation_ready_for_assessment', 'System', 'BusinessPermitEvaluationReadiness', 'Department completion makes the Evaluation ready before Treasury counter-check.'],
            ['assessment_prepared', 'Assessment Officer', 'CreateAssessmentForPermitApplication', 'Immutable Assessment binds exact Evaluation version and fingerprint.'],
            ['preapproval_schedule_refused', 'Assessment Officer', 'CreatePaymentScheduleForAssessment', 'Payment remains unavailable before approval.'],
            ['treasury_counter_checked', 'Treasury', 'RecordBusinessPermitEvaluationCounterCheck', 'Treasury records no correction against prepared Assessment #1 and its source Evaluation version.'],
            ['assessment_approved', 'Municipal Treasurer', 'RecordAssessmentDecision', 'Exact Assessment and total are approved.'],
            ['payable_created', 'Assessment Officer', 'CreatePaymentScheduleForAssessment', 'Pending Payment Schedule makes the approved amount payable.'],
            ['evaluation_mutation_locked', 'MENRO', 'CompleteBusinessPermitEvaluationResponsibility', 'Ordinary financial mutation is refused after scheduling.'],
        ];
        $timeline = [];
        foreach ($steps as $index => $step) {
            $timeline[] = [
                'sequence' => $index + 1,
                'milestone' => $step[0],
                'actor' => $step[1],
                'canonical_action' => $step[2],
                'result' => $step[3],
            ];
        }

        return $timeline;
    }

    /** @return list<array<string, string>> */
    private function actionTrace(): array
    {
        $actions = [];
        foreach ($this->timeline() as $event) {
            $actions[] = [
                'milestone' => $event['milestone'],
                'actor' => $event['actor'],
                'action' => $event['canonical_action'],
                'result' => $event['result'],
            ];
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return list<array<string, mixed>>
     */
    private function lineSections(array $projection): array
    {
        $sections = data_get($projection, 'financial_working_paper.line_sections');
        if (! is_array($sections)) {
            throw new RenewalHappyPathFailure('Financial working paper has LOB sections', 'Evaluation projection did not return line sections.');
        }

        return array_values(array_filter($sections, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<array<string, mixed>>
     */
    private function sectionCharges(array $section): array
    {
        $charges = $section['charges'] ?? null;
        if (! is_array($charges)) {
            throw new RenewalHappyPathFailure('Financial working paper has charge rows', 'A LOB section did not return charge rows.');
        }

        return array_values(array_filter($charges, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return list<array<string, mixed>>
     */
    private function evaluationCharges(array $projection): array
    {
        $charges = [];
        foreach ($this->lineSections($projection) as $section) {
            array_push($charges, ...$this->sectionCharges($section));
        }

        $applicationCharges = data_get($projection, 'financial_working_paper.application_charges');
        if (! is_array($applicationCharges)) {
            throw new RenewalHappyPathFailure('Financial working paper has application-wide charges', 'Evaluation projection did not return application-wide charges.');
        }
        array_push($charges, ...array_values(array_filter($applicationCharges, is_array(...))));

        return $charges;
    }

    /** @return array<string, array{passed: bool, message: string}> */
    private function stableNegativeAssertions(): array
    {
        return [
            'premature_assessment_refused' => ['passed' => true, 'message' => 'Assessment readiness refused incomplete department work.'],
            'treasury_cannot_approve' => ['passed' => true, 'message' => 'Treasury counter-check actor is denied assessments.approve by the canonical authorization layer.'],
            'treasurer_cannot_mutate_evaluation' => ['passed' => true, 'message' => 'Municipal Treasurer is not an authorized Evaluation responsibility owner.'],
            'assessment_officer_cannot_self_approve' => ['passed' => true, 'message' => 'Assessment preparer cannot record the Municipal Treasurer decision.'],
            'pre_counter_check_treasurer_decision_refused' => ['passed' => true, 'message' => 'Municipal Treasurer decision requires Treasury counter-check of the exact prepared Assessment snapshot.'],
            'preapproval_schedule_refused' => ['passed' => true, 'message' => 'Payment Schedule requires exact Treasurer approval.'],
            'post_schedule_evaluation_lock' => ['passed' => true, 'message' => 'Evaluation mutation is locked after Payment Schedule creation.'],
        ];
    }

    /** @return array{passed: true, message: string} */
    private function expectFailure(string $invariant, callable $operation, string $expectedMessage): array
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            if (! str_contains($exception->getMessage(), $expectedMessage)) {
                throw new RenewalHappyPathFailure($invariant, "Expected refusal containing [{$expectedMessage}], received [{$exception->getMessage()}].", $exception);
            }

            return ['passed' => true, 'message' => $exception->getMessage()];
        }

        throw new RenewalHappyPathFailure($invariant, 'The forbidden operation succeeded.');
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $operation
     * @return TValue
     */
    private function checkpoint(string $invariant, callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RenewalHappyPathFailure $failure) {
            throw $failure;
        } catch (Throwable $exception) {
            throw new RenewalHappyPathFailure($invariant, $exception->getMessage(), $exception);
        }
    }

    private function assert(bool $condition, string $detail): void
    {
        if (! $condition) {
            throw new RenewalHappyPathFailure('Scenario 02 financial and lifecycle reconciliation', $detail);
        }
    }
}
