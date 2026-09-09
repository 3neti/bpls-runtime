<?php

namespace App\Actions;

use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\Models\LifecycleCleanroomRun;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartLifecycleCleanroom
{
    public function __construct(
        private readonly StakeholderPreviewSafety $safety,
        private readonly LifecycleCleanroomDefinition $definition,
        private readonly EnsureProductLabLineOfBusinessCatalog $ensureCatalog,
        private readonly BuildSourceBackedNewApplicationIntake $buildSourceBackedIntake,
        private readonly ProvisionLifecycleLaboratoryActors $provisionActors,
    ) {}

    public function handle(
        User $startedBy,
        string $ceremony = LifecycleCleanroomRun::CeremonyLegacyRegression,
        ?string $sourceSpecimenId = null,
    ): LifecycleCleanroomRun {
        $this->safety->ensureReady();
        $this->ensureCatalog->handle();

        if ($sourceSpecimenId !== null
            && ($ceremony !== LifecycleCleanroomRun::CeremonyNelsonReconciliationV1
                || $sourceSpecimenId !== LifecycleCleanroomRun::SourceSpecimenCal2026001New2025)) {
            throw new \InvalidArgumentException('The requested lifecycle source specimen is unsupported.');
        }
        $sourceSpecimen = $sourceSpecimenId === null
            ? null
            : $this->buildSourceBackedIntake->handle()['source_specimen'];

        return DB::transaction(function () use ($startedBy, $ceremony, $sourceSpecimenId, $sourceSpecimen): LifecycleCleanroomRun {
            $existing = LifecycleCleanroomRun::query()
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($existing instanceof LifecycleCleanroomRun) {
                $existingCeremony = data_get($existing->actor_manifest, 'ceremony', LifecycleCleanroomRun::CeremonyLegacyRegression);
                $existingSourceSpecimenId = data_get($existing->actor_manifest, 'source_specimen.id');
                if ($existingCeremony !== $ceremony || $existingSourceSpecimenId !== $sourceSpecimenId) {
                    throw new \RuntimeException('Close the active lifecycle cleanroom before starting a different ceremony or source specimen.');
                }

                return $existing;
            }

            $publicId = (string) Str::ulid();
            /** @var array<string, array{label: string, user_id: int, role_id: int}> $actors */
            $actors = [];
            foreach ($this->provisionActors->handle() as $key => $user) {
                $definition = $this->definition->actors()[$key];
                $role = $user->primaryRole() ?? throw new \RuntimeException("Lifecycle laboratory actor [{$key}] has no role.");
                $actors[$key] = [
                    'label' => $definition['label'],
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ];
            }

            return LifecycleCleanroomRun::query()->create([
                'public_id' => $publicId,
                'status' => 'active',
                'started_by_id' => $startedBy->id,
                'actor_manifest' => [
                    'revision' => LifecycleCleanroomDefinition::Revision,
                    'ceremony' => $ceremony,
                    'source_specimen' => $sourceSpecimen,
                    'actors' => $actors,
                    'actor_user_ids' => collect($actors)->pluck('user_id')->sort()->values()->all(),
                    'actor_role_ids' => collect($actors)->pluck('role_id')->unique()->sort()->values()->all(),
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
                ],
                'owned_resource_manifest' => [
                    'ceremony' => $ceremony,
                    'source_specimen_id' => $sourceSpecimenId,
                    'user_ids' => [],
                    'provisioned_actor_user_ids' => collect($actors)->pluck('user_id')->sort()->values()->all(),
                    'business_owner_ids' => [],
                    'business_ids' => [],
                    'permit_application_ids' => [],
                    'permit_application_declaration_ids' => [],
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
                ],
            ]);
        }, 3);
    }
}
