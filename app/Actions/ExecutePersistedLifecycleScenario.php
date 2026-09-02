<?php

namespace App\Actions;

use App\LifecycleScenarios\LifecycleScenarioSpecimenOwnership;
use App\LifecycleScenarios\NewApplicationHappyPathDefinition;
use App\LifecycleScenarios\NewApplicationHappyPathScenario;
use App\LifecycleScenarios\RenewalHappyPathDefinition;
use App\LifecycleScenarios\RenewalHappyPathFailure;
use App\LifecycleScenarios\RenewalHappyPathScenario;
use App\Models\Assessment;
use App\Models\LifecycleScenarioSpecimen;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExecutePersistedLifecycleScenario
{
    public function __construct(
        private readonly NewApplicationHappyPathScenario $newApplication,
        private readonly RenewalHappyPathScenario $renewal,
        private readonly NewApplicationHappyPathDefinition $newApplicationDefinition,
        private readonly RenewalHappyPathDefinition $renewalDefinition,
        private readonly InspectBplsInstallation $inspectInstallation,
        private readonly LifecycleScenarioSpecimenOwnership $specimenOwnership,
    ) {}

    /** @return list<string> */
    public static function scenarioIds(): array
    {
        return [NewApplicationHappyPathDefinition::Id, RenewalHappyPathDefinition::Id];
    }

    /** @return array<string, mixed> */
    public function handle(string $scenarioId): array
    {
        [$scenario, $definition, $revision] = match ($scenarioId) {
            NewApplicationHappyPathDefinition::Id => [$this->newApplication, $this->newApplicationDefinition, NewApplicationHappyPathDefinition::Revision],
            RenewalHappyPathDefinition::Id => [$this->renewal, $this->renewalDefinition, RenewalHappyPathDefinition::Revision],
            default => throw new RenewalHappyPathFailure('Requested lifecycle scenario is implemented', "Scenario [{$scenarioId}] is NOT RUN."),
        };

        $installation = $this->inspectInstallation->handle();
        if (! $installation['integrity']['pass']) {
            throw new RenewalHappyPathFailure('Scenario starts from bpls:install', 'Installed baseline integrity failed: '.implode(' ', $installation['integrity']['issues']));
        }

        $description = $definition->describe();
        $existing = LifecycleScenarioSpecimen::query()
            ->where('scenario_id', $scenarioId)
            ->where('scenario_revision', $revision)
            ->first();

        return DB::transaction(function () use ($scenario, $existing, $description, $installation, $revision): array {
            if (! $existing instanceof LifecycleScenarioSpecimen && ! $installation['zero_state']['is_empty']) {
                try {
                    $this->specimenOwnership->assertTransactionalResidueIsExplicitlyOwned();
                } catch (Throwable $exception) {
                    throw new RenewalHappyPathFailure(
                        'Persisted lifecycle specimens own all pre-existing transactional residue',
                        'Unrelated or multiply claimed transaction records exist; refusing to delete, claim, replace, or block them. '.$exception->getMessage(),
                        $exception,
                    );
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
                $this->specimenOwnership->assertDisjointFromPersistedSpecimens($manifest);
            } catch (Throwable $exception) {
                throw new RenewalHappyPathFailure(
                    'Persisted lifecycle specimen manifests are disjoint',
                    'The new specimen overlaps resources explicitly owned by another specimen; no claim or replacement was attempted.',
                    $exception,
                );
            }

            LifecycleScenarioSpecimen::query()->create([
                'scenario_id' => $description['id'],
                'scenario_revision' => $revision,
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
        $application = PermitApplication::query()->with([
            'business.owner',
            'lines',
            'businessPermitEvaluation.versions.revisions',
            'businessPermitEvaluation.items.revisions',
            'assessments.lines',
            'assessments.decision',
            'assessments.treasuryCounterCheck',
            'paymentSchedules.lines',
        ])->findOrFail($this->resultInteger($result, 'application.id'));
        $assessment = Assessment::query()->findOrFail($this->resultInteger($result, 'assessment.id'));
        $reusesNewApplicationIdentity = $result['scenario_id'] === RenewalHappyPathDefinition::Id
            && LifecycleScenarioSpecimen::query()
                ->where('scenario_id', NewApplicationHappyPathDefinition::Id)
                ->where('scenario_revision', NewApplicationHappyPathDefinition::Revision)
                ->exists();

        $manifest = [
            'business_owner_ids' => $reusesNewApplicationIdentity ? [] : [$application->business->business_owner_id],
            'business_ids' => $reusesNewApplicationIdentity ? [] : [$application->business_id],
            'referenced_business_owner_ids' => $reusesNewApplicationIdentity ? [$application->business->business_owner_id] : [],
            'referenced_business_ids' => $reusesNewApplicationIdentity ? [$application->business_id] : [],
            'reference_line_of_business_ids' => $application->lines->pluck('line_of_business_id')->filter()->sort()->values()->all(),
            'permit_application_ids' => [$application->id],
            'permit_application_declaration_ids' => $application->declaration()->pluck('id')->all(),
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
            ->pluck('id')->sort()->values()->all() ?? [];
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
}
