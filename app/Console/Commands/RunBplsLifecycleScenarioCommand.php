<?php

namespace App\Console\Commands;

use App\Actions\InspectBplsInstallation;
use App\LifecycleScenarios\LifecycleScenarioSpecimenOwnership;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\NewApplicationHappyPathFailure;
use App\LifecycleScenarios\NewApplicationHappyPathScenario;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathFailure;
use App\LifecycleScenarios\RenewalHappyPathScenario;
use App\LifecycleScenarios\ScenarioArtifactStore;
use App\Models\Assessment;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Throwable;

#[Signature('bpls:lifecycle:run {scenario : Stable BPLS lifecycle scenario id} {--persist : Preserve exactly one harness-owned product specimen} {--json : Emit only the canonical JSON result}')]
#[Description('Run one deterministic BPLS lifecycle certification scenario.')]
class RunBplsLifecycleScenarioCommand extends Command
{
    public function handle(
        RenewalHappyPathScenario $renewalHappyPath,
        NewApplicationHappyPathScenario $newApplicationHappyPath,
        InspectBplsInstallation $inspectInstallation,
        LifecycleScenarioSpecimenOwnership $specimenOwnership,
    ): int {
        $scenarioId = (string) $this->argument('scenario');
        $definition = match ($scenarioId) {
            RenewalHappyPathDefinition::Id => app(RenewalHappyPathDefinition::class),
            NewApplicationHappyPathDefinition::Id => app(NewApplicationHappyPathDefinition::class),
            default => null,
        };
        $scenario = match ($scenarioId) {
            RenewalHappyPathDefinition::Id => $renewalHappyPath,
            NewApplicationHappyPathDefinition::Id => $newApplicationHappyPath,
            default => null,
        };
        $runId = $scenarioId === NewApplicationHappyPathDefinition::Id
            ? NewApplicationHappyPathDefinition::RunId
            : RenewalHappyPathDefinition::RunId;
        $artifactStore = new ScenarioArtifactStore($scenarioId, $runId);

        if ($definition === null || $scenario === null) {
            return $this->failure($artifactStore, new RenewalHappyPathFailure(
                'Requested lifecycle scenario is implemented',
                "Scenario [{$scenarioId}] is NOT RUN. Use bpls:lifecycle:list.",
            ));
        }

        try {
            $installation = $inspectInstallation->handle();
            if (! $installation['integrity']['pass']) {
                throw new RenewalHappyPathFailure('Scenario starts from bpls:install', 'Installed baseline integrity failed: '.implode(' ', $installation['integrity']['issues']));
            }

            $result = $this->option('persist')
                ? $this->runPersisted($scenario, $definition, $installation, $specimenOwnership)
                : $this->runCertification($scenario, $installation);
            $artifactStore->putJson('result.json', $result);
            $artifactStore->putJson('action-trace.json', ['actions' => $result['action_trace']]);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $this->renderHumanResult($result, $artifactStore, (bool) $this->option('persist'));

            return self::SUCCESS;
        } catch (RenewalHappyPathFailure|NewApplicationHappyPathFailure $failure) {
            return $this->failure($artifactStore, $failure);
        } catch (Throwable $exception) {
            return $this->failure($artifactStore, new RenewalHappyPathFailure(
                'Lifecycle scenario completed without an unclassified engine exception',
                $exception->getMessage(),
                $exception,
            ));
        }
    }

    /** @param array<string, mixed> $result */
    private function renderHumanResult(array $result, ScenarioArtifactStore $artifactStore, bool $persisted): void
    {
        $this->info($result['scenario_id'] === RenewalHappyPathDefinition::Id
            ? 'BPLS LIFECYCLE SCENARIO 01 — RENEWAL HAPPY PATH: PASS'
            : 'BPLS LIFECYCLE SCENARIO 02 — NEW APPLICATION HAPPY PATH: PASS');
        $this->line('Question: '.$result['business_question']);
        $this->newLine();
        $this->line('SYSTEM BOOTSTRAP');
        $this->line('[PASS] '.$result['system_bootstrap']['municipal_runtime_configuration']['municipality'].' · '.$result['system_bootstrap']['municipal_runtime_configuration']['system_name']);
        $this->line('[PASS] Required schema and operational tables available');
        $this->line('[PASS] '.count($result['system_bootstrap']['actor_capabilities']).' synthetic role/capability sets available');
        $this->line('[PASS] Business Inspection Fee · accepted governed municipal rule · '.$this->pesos($result['system_bootstrap']['accepted_business_inspection_fee']['amount_cents']).' · NOT provisional_uat');
        $this->line('[PASS] Scenario routing reference LOBs available · provisional_uat');
        $this->newLine();
        $this->line('ONBOARDING');
        $this->line('[PASS] Owner/customer onboarded · '.$result['onboarding']['owner_customer']['name']);
        $this->line('[PASS] Business onboarded · '.$result['onboarding']['business']['name']);
        $this->line('Canonical action: '.$result['onboarding']['canonical_action']);
        $this->newLine();
        $this->line('APPLICATION / LINES OF BUSINESS');
        $this->line('[PASS] '.str($result['application']['type'])->headline().' #'.$result['application']['id'].' lodged · no official application number manufactured');
        foreach ($result['lines_of_business'] as $lineOfBusiness) {
            $this->line('[PASS] '.$lineOfBusiness['name'].' declared');
        }
        $this->newLine();
        $this->line('APPLICATION EVALUATION ROUTING');
        $this->line('Canonical noun: '.$result['application_evaluation_routing']['canonical_noun'].' · projected, not a second persisted aggregate');
        foreach ($result['application_evaluation_routing']['groups'] as $group) {
            $this->line($group['line_of_business_name']);
            foreach ($group['required_work'] as $work) {
                $this->line('  '.$work['work_label'].' · '.$work['department'].' · Required · '.$work['reason']);
            }
        }
        $this->line('[PASS] Routing required work = generated responsibilities exactly · '.$result['responsibilities']['created_count']);
        $this->newLine();
        $this->line('DEPARTMENT RESPONSIBILITIES');
        $this->line('[PASS] '.$result['responsibilities']['resolved_count'].'/'.$result['responsibilities']['created_count'].' resolved · six departmental amounts are provisional_uat');
        $this->newLine();
        $this->line('FINANCIAL WORKING PAPER');

        foreach ($result['lines_of_business'] as $lineOfBusiness) {
            $this->line($lineOfBusiness['name']);
            foreach ($lineOfBusiness['charges'] as $charge) {
                $this->line('  '.$charge['label'].' · '.$charge['responsible_party'].' · '.$this->pesos($charge['amount_cents']));
            }
            $this->line('  Subtotal: '.$this->pesos($lineOfBusiness['subtotal_amount_cents']));
        }

        $this->line('Application-wide');
        $this->line('  Business Inspection Fee · accepted governed rule · '.$this->pesos($result['evaluation']['subtotals']['application_wide_amount_cents']));
        $this->line('Grand Total: '.$this->pesos($result['evaluation']['grand_total_amount_cents']));
        $this->newLine();
        $this->line('Evaluation: Ready for Assessment before Treasury counter-check · version '.$result['evaluation']['version_sequence']);
        $this->line('Assessment: #'.$result['assessment']['id'].' · '.$this->pesos($result['assessment']['total_amount_cents']).' · Prepared by '.$result['assessment']['prepared_by']['name'].' · immutable');
        $this->line('Treasury: Assessment #'.$result['treasury_counter_check']['assessment_id'].' · source Evaluation version '.$result['treasury_counter_check']['evaluation_version_sequence'].' · completed, no correction');
        $this->line('Municipal Treasurer: approved exact Assessment');
        $this->line('Payable: Payment Schedule #'.$result['payment_schedule']['id'].' · '.$this->pesos($result['payable']['amount_cents']));
        $this->line('Database: '.$result['database_driver'].' · Semantic result '.$result['semantic_result_hash']);
        $this->line('JSON: '.$artifactStore->absolutePath().'/result.json');

        if ($persisted) {
            $applicationId = $result['application']['id'];
            $this->newLine();
            $this->line('PERSISTED PRODUCT SPECIMEN');
            $this->line('Scenario · '.$result['scenario_id'].' · '.$result['scenario_revision']);
            $this->line('Business · '.$result['onboarding']['business']['name']);
            $this->line('Application · #'.$applicationId);
            $this->line('Citizen lens · '.route('citizen.permit-applications.show', $applicationId));
            $this->line('BPLO lens · '.route('staff.permit-applications.show', $applicationId));
            $this->line('Assessment Officer · '.route('staff.permit-applications.assessments.show', $result['assessment']['id']));
            $this->line('Treasury / Municipal Treasurer · '.route('staff.permit-applications.show', $applicationId));
            $this->line('Assessment · '.$this->pesos($result['assessment']['total_amount_cents']));
            $this->line('Payment Schedule · '.$this->pesos($result['payment_schedule']['total_amount_cents']).' pending');
        }
    }

    /**
     * @param  array<string, mixed>  $installation
     * @return array<string, mixed>
     */
    private function runCertification(RenewalHappyPathScenario|NewApplicationHappyPathScenario $scenario, array $installation): array
    {
        if (! $installation['zero_state']['is_empty']) {
            throw new RenewalHappyPathFailure('Certification run is disposable and isolated', 'Certification requires a zero-transaction installed database. Use --persist only for the curated product specimen.');
        }

        DB::beginTransaction();
        try {
            return $scenario->run();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $installation
     * @return array<string, mixed>
     */
    private function runPersisted(
        RenewalHappyPathScenario|NewApplicationHappyPathScenario $scenario,
        RenewalHappyPathDefinition|NewApplicationHappyPathDefinition $definition,
        array $installation,
        LifecycleScenarioSpecimenOwnership $specimenOwnership,
    ): array {
        $description = $definition->describe();
        $existing = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', $description['id'])
            ->where('scenario_revision', $resultRevision = $scenario instanceof NewApplicationHappyPathScenario ? NewApplicationHappyPathDefinition::Revision : RenewalHappyPathDefinition::Revision)
            ->first();

        return DB::transaction(function () use ($scenario, $existing, $description, $installation, $resultRevision, $specimenOwnership): array {
            if (! $existing instanceof LifecycleScenarioSpecimen && ! $installation['zero_state']['is_empty']) {
                try {
                    $specimenOwnership->assertTransactionalResidueIsExplicitlyOwned();
                } catch (Throwable $exception) {
                    throw new RenewalHappyPathFailure('Persisted lifecycle specimens own all pre-existing transactional residue', 'Unrelated or multiply claimed transaction records exist; refusing to delete, claim, replace, or block them. '.$exception->getMessage(), $exception);
                }
            }

            $result = $scenario->run();
            $manifest = $this->ownedResourceManifest($result);

            if ($existing instanceof LifecycleScenarioSpecimen) {
                if ($existing->permit_application_id !== $this->resultInteger($result, 'application.id')
                    || ! hash_equals($existing->semantic_result_hash, $this->resultString($result, 'semantic_result_hash'))
                    || $existing->owned_resource_manifest !== $manifest) {
                    throw new RenewalHappyPathFailure('Persisted specimen rerun is byte-stable', 'Existing harness ownership or certified semantics changed; no replacement was attempted.');
                }

                return $result;
            }

            try {
                $specimenOwnership->assertDisjointFromPersistedSpecimens($manifest);
            } catch (Throwable $exception) {
                throw new RenewalHappyPathFailure('Persisted lifecycle specimen manifests are disjoint', 'The new specimen overlaps resources explicitly owned by another specimen; no claim or replacement was attempted.', $exception);
            }

            LifecycleScenarioSpecimen::query()->create([
                'scenario_id' => $description['id'],
                'scenario_revision' => $resultRevision,
                'permit_application_id' => $this->resultInteger($result, 'application.id'),
                'semantic_result_hash' => $this->resultString($result, 'semantic_result_hash'),
                'owned_resource_manifest' => $manifest,
            ]);

            return $result;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function ownedResourceManifest(array $result): array
    {
        $applicationId = $this->resultInteger($result, 'application.id');
        $assessmentId = $this->resultInteger($result, 'assessment.id');
        $application = PermitApplication::query()->with([
            'business.owner',
            'lines',
            'businessPermitEvaluation.versions.revisions',
            'businessPermitEvaluation.items.revisions',
            'assessments.lines',
            'assessments.decision',
            'assessments.treasuryCounterCheck',
            'paymentSchedules.lines',
        ])->findOrFail($applicationId);
        $assessment = Assessment::query()->findOrFail($assessmentId);

        $manifest = [
            'business_owner_ids' => [$application->business->business_owner_id],
            'business_ids' => [$application->business_id],
            'line_of_business_ids' => $application->lines->pluck('line_of_business_id')->filter()->sort()->values()->all(),
            'permit_application_ids' => [$application->id],
            'permit_application_line_ids' => $application->lines->pluck('id')->sort()->values()->all(),
            'permit_clearance_ids' => $application->clearances()->pluck('id')->sort()->values()->all(),
            'office_charge_contribution_ids' => $application->officeChargeContributions()->pluck('id')->sort()->values()->all(),
            'evaluation_ids' => [$application->businessPermitEvaluation?->id],
            'evaluation_version_ids' => $application->businessPermitEvaluation?->versions->pluck('id')->sort()->values()->all() ?? [],
            'evaluation_item_ids' => $application->businessPermitEvaluation?->items->pluck('id')->sort()->values()->all() ?? [],
            'evaluation_revision_ids' => $application->businessPermitEvaluation?->versions->flatMap->revisions->pluck('id')->sort()->values()->all() ?? [],
            'assessment_ids' => [$assessment->id],
            'assessment_line_ids' => $assessment->lines()->pluck('id')->sort()->values()->all(),
            'treasury_counter_check_ids' => [$this->resultInteger($result, 'treasury_counter_check.id')],
            'assessment_decision_ids' => [$assessment->decision?->id],
            'payment_schedule_ids' => [$this->resultInteger($result, 'payment_schedule.id')],
            'payment_schedule_line_ids' => $application->paymentSchedules->flatMap->lines->pluck('id')->sort()->values()->all(),
            'treasury_collection_ids' => $application->treasuryCollections()->pluck('id')->sort()->values()->all(),
            'collection_allocation_ids' => [],
            'receipt_ids' => [],
            'provisional_permit_completion_ids' => $application->provisionalUatPermitCompletion()->pluck('id')->sort()->values()->all(),
            'actor_user_ids' => $this->actorUserIds($result),
            'actor_role_ids' => User::query()->whereKey($this->actorUserIds($result))->pluck('role_id')->filter()->unique()->sort()->values()->all(),
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
        ];

        $notificationIds = $application->submittedBy?->notifications()
            ->get()
            ->filter(fn ($notification): bool => data_get($notification->data, 'permit_application_id') === $application->id)
            ->pluck('id')
            ->sort()
            ->values()
            ->all() ?? [];

        if ($notificationIds !== []) {
            $manifest['database_notification_ids'] = $notificationIds;
        }

        return $manifest;
    }

    /** @param array<string, mixed> $result */
    private function resultInteger(array $result, string $path): int
    {
        $value = data_get($result, $path);

        if (! is_int($value)) {
            throw new RenewalHappyPathFailure('Scenario result contains typed persisted identifiers', "Scenario result [{$path}] is not an integer.");
        }

        return $value;
    }

    /** @param array<string, mixed> $result */
    private function resultString(array $result, string $path): string
    {
        $value = data_get($result, $path);

        if (! is_string($value)) {
            throw new RenewalHappyPathFailure('Scenario result contains typed persisted identifiers', "Scenario result [{$path}] is not a string.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<int>
     */
    private function actorUserIds(array $result): array
    {
        $capabilities = data_get($result, 'system_bootstrap.actor_capabilities');
        if (! is_iterable($capabilities)) {
            throw new RenewalHappyPathFailure('Scenario result contains actor capability evidence', 'Scenario actor capability evidence is unavailable.');
        }

        $userIds = [];
        foreach ($capabilities as $capability) {
            if (is_array($capability) && is_int($capability['user_id'] ?? null)) {
                $userIds[] = $capability['user_id'];
            }
        }

        sort($userIds);

        return $userIds;
    }

    private function failure(ScenarioArtifactStore $artifactStore, RenewalHappyPathFailure|NewApplicationHappyPathFailure $failure): int
    {
        $isScenario02 = $artifactStore->scenarioKey === NewApplicationHappyPathDefinition::Id;
        $result = [
            'schema_version' => 'bpls.lifecycle-certification.v1',
            'scenario_id' => $artifactStore->scenarioKey,
            'scenario_revision' => $isScenario02 ? NewApplicationHappyPathDefinition::Revision : RenewalHappyPathDefinition::Revision,
            'status' => 'failed',
            'business_question' => $isScenario02 ? NewApplicationHappyPathDefinition::EvidenceQuestion : RenewalHappyPathDefinition::EvidenceQuestion,
            'first_failure' => [
                'invariant' => $failure->invariant,
                'message' => $failure->getMessage(),
            ],
            'artifacts' => [
                'root' => $artifactStore->rootRelativePath(),
                'json' => 'result.json',
            ],
        ];
        $artifactStore->putJson('result.json', $result);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->error($failure->getMessage());
            $this->line('JSON: '.$artifactStore->absolutePath().'/result.json');
        }

        return self::FAILURE;
    }

    private function pesos(int $amountCents): string
    {
        return 'PHP '.Number::format($amountCents / 100, precision: 2);
    }
}
