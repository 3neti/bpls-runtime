<?php

namespace App\Actions;

use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\Models\LifecycleCleanroomRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StartLifecycleCleanroom
{
    public function __construct(
        private readonly StakeholderPreviewSafety $safety,
        private readonly LifecycleCleanroomDefinition $definition,
        private readonly EnsureProductLabLineOfBusinessCatalog $ensureCatalog,
        private readonly BuildSourceBackedNewApplicationIntake $buildSourceBackedIntake,
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
            $actors = [];
            foreach ($this->definition->actors() as $key => $definition) {
                $role = Role::query()->firstOrCreate(
                    ['code' => 'lifecycle-cleanroom-'.$key],
                    ['name' => 'Cleanroom '.$definition['label'], 'description' => 'Synthetic Lifecycle Laboratory role; never a production municipal assignment.'],
                );
                $role->permissions()->sync(collect($definition['permissions'])->map(
                    fn ($permission): int => Permission::query()->firstOrCreate(
                        ['code' => $permission->value],
                        ['name' => str($permission->value)->replace('.', ' ')->title()->toString()],
                    )->id,
                ));
                $user = User::query()->create([
                    'role_id' => $role->id,
                    'name' => 'Cleanroom '.Str::substr($publicId, -6).' '.$definition['label'],
                    'email' => 'cleanroom-'.Str::lower($publicId).'-'.$key.'@example.test',
                    'password' => Hash::make(Str::random(48)),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
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
                    'user_ids' => collect($actors)->pluck('user_id')->sort()->values()->all(),
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
