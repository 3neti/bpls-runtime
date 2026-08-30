<?php

namespace App\LifecycleScenarios;

use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\CreatePaymentScheduleForAssessment;
use App\Actions\CreatePermitApplication;
use App\Actions\DefineBusinessPermitEvaluationItem;
use App\Actions\InitializeBusinessPermitEvaluation;
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
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluation;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\PaymentSchedule;
use App\Models\Permission;
use App\Models\PermitApplication;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

final class RenewalHappyPathScenario
{
    public function __construct(
        private readonly RenewalHappyPathDefinition $definition,
        private readonly CreatePermitApplication $createPermitApplication,
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
        $existing = $this->existingApplication();

        if ($existing instanceof PermitApplication) {
            return $this->checkpoint('Deterministic rerun found stale or nonconformant state', fn (): array => $this->result($existing));
        }

        return DB::transaction(function (): array {
            $actors = $this->actors();
            $linesOfBusiness = $this->linesOfBusiness();
            $this->assertAcceptedInspectionRule();

            $application = $this->checkpoint('Renewal was not lodged through canonical staff intake', function () use ($actors, $linesOfBusiness): PermitApplication {
                $applicationLines = [];
                foreach ($this->definition->linesOfBusiness() as $line) {
                    $applicationLines[] = [
                        'line_of_business_id' => $linesOfBusiness[$line['code']]->id,
                        'declared_gross_sales_cents' => $line['declared_gross_sales_cents'],
                        'capital_investment_cents' => $line['capital_investment_cents'],
                        'quantity' => 1,
                    ];
                }

                $application = $this->createPermitApplication->handle([
                    'owner_name' => 'Scenario 01 Synthetic Owner',
                    'owner_email' => null,
                    'owner_phone' => null,
                    'owner_address' => 'Synthetic Ipil product laboratory address',
                    'business_name' => 'Scenario 01 Market and Kitchen',
                    'trade_name' => 'Scenario 01 Renewal Laboratory',
                    'registration_number' => 'S01-RENEWAL-HAPPY-PATH',
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
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
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

                return $application;
            });

            $evaluation = $this->checkpoint('Evaluation did not initialize from the lodged Renewal', fn (): BusinessPermitEvaluation => $this->initializeEvaluation->handle($application, $actors['assessment_officer']));

            $this->expectFailure(
                'Assessment preparation before department completion was not safely refused',
                fn (): Assessment => $this->createAssessment->handle($application->fresh(), $actors['assessment_officer']),
                'Business Permit Evaluation is not Ready for Assessment',
            );
            $this->assert($application->assessments()->count() === 0, 'Premature Assessment refusal left a persisted Assessment.');

            $this->checkpoint('Required departmental responsibilities were not created', function () use ($evaluation, $actors, $linesOfBusiness): void {
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
            });

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
                        RenewalHappyPathDefinition::Id.':'.$responsibility['key'].':complete',
                    );
                }

                $completed = $this->scenarioResponsibilityProjection($evaluation->fresh());
                $this->assert(collect($completed)->every(fn (array $item): bool => $item['resolution'] === 'resolved'), 'At least one department responsibility remained unresolved.');
                $this->assert(collect($completed)->every(fn (array $item): bool => $item['inspection_completed'] === true), 'At least one required inspection/review was not completed.');
            });

            $beforeCounterCheck = $this->evaluationReadiness->forAssessment($evaluation->fresh(), 'provisional_uat');
            $this->assert(! $beforeCounterCheck['ready'], 'Evaluation became Ready before Treasury counter-check.');
            $this->assert(collect($beforeCounterCheck['issues'])->contains(fn (string $issue): bool => str_contains($issue, 'Treasury counter-check')), 'Pre-counter-check readiness did not identify the exact Treasury gate.');

            $this->checkpoint('Treasury counter-checker could approve the Assessment', function () use ($actors): void {
                $this->assert($actors['treasury']->cannot(UserPermission::ApproveAssessments->value), 'Treasury counter-checker unexpectedly has assessments.approve.');
                $this->assert($actors['municipal_treasurer']->can(UserPermission::ApproveAssessments->value), 'Municipal Treasurer lacks assessments.approve.');
            });

            $evaluationVersion = $evaluation->fresh()->currentVersion;
            $this->checkpoint('Treasury counter-check did not bind the current Evaluation version', fn () => $this->recordCounterCheck->handle(
                $evaluation->fresh(),
                $actors['treasury'],
                'No correction: Treasury reconciled the exact current Evaluation working paper.',
                $evaluationVersion->sequence,
                $evaluationVersion->fingerprint,
            ));

            $ready = $this->evaluationReadiness->forAssessment($evaluation->fresh(), 'provisional_uat');
            $this->assert($ready['ready'], 'Evaluation did not become Ready after all department work and Treasury counter-check: '.implode(' ', $ready['issues']));

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
                        RenewalHappyPathDefinition::Id.':unauthorized-treasurer-mutation',
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
                'Payment Schedule was created before exact Treasurer approval',
                fn (): PaymentSchedule => $this->createPaymentSchedule->handle($assessment, $actors['assessment_officer']),
                'approved by the Municipal Treasurer',
            );
            $this->assert($application->paymentSchedules()->count() === 0, 'Pre-approval Payment Schedule refusal left a persisted schedule.');

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
                        RenewalHappyPathDefinition::Id.':post-schedule-mutation',
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
        $application->load(['business.owner', 'lines.lineOfBusiness', 'businessPermitEvaluation']);
        $evaluation = $application->businessPermitEvaluation;
        $this->assert($evaluation instanceof BusinessPermitEvaluation, 'Persisted scenario application has no Evaluation.');
        $projection = $this->evaluationResolver->resolve($evaluation->fresh());
        $readiness = $this->evaluationReadiness->forAssessment($evaluation->fresh(), 'provisional_uat');
        $responsibilities = $this->scenarioResponsibilityProjection($evaluation->fresh());
        $assessment = $application->assessments()
            ->whereNull('superseded_at')
            ->with(['lines.lineOfBusiness', 'assessedBy', 'decision.decidedBy'])
            ->sole();
        $schedule = $application->paymentSchedules()->with('lines')->sole();
        $decision = $assessment->decision;

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
            'status' => 'passed',
            'business_question' => RenewalHappyPathDefinition::EvidenceQuestion,
            'evidence' => $definition['evidence'],
            'first_failure' => null,
            'semantic_result_hash' => $semanticHash,
            'database_driver' => DB::connection()->getDriverName(),
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
                'id' => $evaluation->fresh()->currentVersion?->counterCheck?->id,
                'status' => 'completed_no_correction',
                'evaluation_version_id' => $projection['version_id'],
                'checked_by' => data_get($evaluation->fresh()->currentVersion?->counterCheck?->checkedBy, 'name'),
                'reason' => $evaluation->fresh()->currentVersion?->counterCheck?->reason,
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
        $applications = PermitApplication::query()
            ->where('metadata->lifecycle_scenario->id', RenewalHappyPathDefinition::Id)
            ->get();

        if ($applications->count() > 1) {
            throw new RenewalHappyPathFailure('Deterministic scenario identity is unique', 'More than one Scenario 01 application exists.');
        }

        return $applications->first();
    }

    /** @return array<string, User> */
    private function actors(): array
    {
        return [
            'intake' => $this->actor('intake', 'Scenario 01 BPLO Intake Officer', [UserPermission::AccessStaff, UserPermission::CreatePermitApplications]),
            'assessment_officer' => $this->actor('assessment-officer', 'Scenario 01 Assessment Officer', [UserPermission::AccessStaff, UserPermission::AssessPermitApplications, UserPermission::PreparePaymentSchedules]),
            'assessor' => $this->actor('assessor', 'Scenario 01 Assessor', [UserPermission::AccessStaff, UserPermission::ContributeBusinessPermitEvaluations]),
            'engineering' => $this->actor('engineering', 'Scenario 01 Engineering Officer', [UserPermission::AccessStaff, UserPermission::ContributeBusinessPermitEvaluations]),
            'health' => $this->actor('health', 'Scenario 01 Health Officer', [UserPermission::AccessStaff, UserPermission::ContributeBusinessPermitEvaluations]),
            'menro' => $this->actor('menro', 'Scenario 01 MENRO Officer', [UserPermission::AccessStaff, UserPermission::ContributeBusinessPermitEvaluations]),
            'treasury' => $this->actor('treasury-counter-check', 'Scenario 01 Treasury Counter-checker', [UserPermission::AccessStaff, UserPermission::CounterCheckBusinessPermitEvaluations]),
            'municipal_treasurer' => $this->actor('municipal-treasurer', 'Scenario 01 Municipal Treasurer', [UserPermission::AccessStaff, UserPermission::ApproveAssessments]),
        ];
    }

    /** @param list<UserPermission> $permissions */
    private function actor(string $key, string $name, array $permissions): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'scenario-01-'.$key],
            ['name' => $name, 'description' => 'Synthetic Scenario 01 role; not a production municipal assignment.'],
        );
        $permissionIds = collect($permissions)->map(function (UserPermission $permission): int {
            return Permission::query()->firstOrCreate(
                ['code' => $permission->value],
                ['name' => str($permission->value)->replace('.', ' ')->title()->toString()],
            )->id;
        });
        $role->permissions()->sync($permissionIds);

        $user = User::query()->firstOrCreate(
            ['email' => 'scenario-01-'.$key.'@example.test'],
            [
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make('scenario-01-not-a-login-credential'),
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
                        'scenario_id' => RenewalHappyPathDefinition::Id,
                        'semantic_classification' => 'provisional_uat',
                        'production_liability' => false,
                    ],
                ],
            );
            $this->assert(data_get($lineOfBusiness->metadata, 'scenario_id') === RenewalHappyPathDefinition::Id, "LOB code [{$definition['code']}] is occupied by non-scenario data.");

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

    /** @return list<array<string, mixed>> */
    private function timeline(): array
    {
        $steps = [
            ['renewal_lodged', 'BPLO Intake Officer', 'CreatePermitApplication', 'Renewal lodged with two declared LOBs.'],
            ['evaluation_initialized', 'Assessment Officer', 'InitializeBusinessPermitEvaluation', 'Applicant LOB facts enter versioned Evaluation.'],
            ['premature_assessment_refused', 'Assessment Officer', 'CreateAssessmentForPermitApplication', 'Readiness guard refuses incomplete department work.'],
            ['responsibilities_created', 'Assessment Officer', 'DefineBusinessPermitEvaluationItem', 'Six required department charge responsibilities are created.'],
            ['departments_completed', 'Concerned Offices', 'CompleteBusinessPermitEvaluationResponsibility', 'Six confirmations resolve applicability, review, and amounts.'],
            ['treasury_counter_checked', 'Treasury', 'RecordBusinessPermitEvaluationCounterCheck', 'Treasury records no correction against the current version.'],
            ['evaluation_ready', 'System', 'BusinessPermitEvaluationReadiness', 'All canonical readiness gates pass.'],
            ['assessment_prepared', 'Assessment Officer', 'CreateAssessmentForPermitApplication', 'Immutable Assessment binds exact Evaluation version and fingerprint.'],
            ['preapproval_schedule_refused', 'Assessment Officer', 'CreatePaymentScheduleForAssessment', 'Payment remains unavailable before approval.'],
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
            'treasury_cannot_approve' => ['passed' => true, 'message' => 'Treasury counter-check actor is denied assessments.approve.'],
            'treasurer_cannot_mutate_evaluation' => ['passed' => true, 'message' => 'Municipal Treasurer is not an authorized Evaluation responsibility owner.'],
            'assessment_officer_cannot_self_approve' => ['passed' => true, 'message' => 'Assessment preparer cannot record the Municipal Treasurer decision.'],
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
            throw new RenewalHappyPathFailure('Scenario 01 financial and lifecycle reconciliation', $detail);
        }
    }
}
