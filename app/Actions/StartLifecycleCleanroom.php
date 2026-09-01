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
    ) {}

    public function handle(User $startedBy): LifecycleCleanroomRun
    {
        $this->safety->ensureReady();
        $this->ensureCatalog->handle();

        return DB::transaction(function () use ($startedBy): LifecycleCleanroomRun {
            $existing = LifecycleCleanroomRun::query()
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($existing instanceof LifecycleCleanroomRun) {
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
                    'actors' => $actors,
                    'actor_user_ids' => collect($actors)->pluck('user_id')->sort()->values()->all(),
                    'actor_role_ids' => collect($actors)->pluck('role_id')->unique()->sort()->values()->all(),
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
                ],
                'owned_resource_manifest' => [
                    'user_ids' => collect($actors)->pluck('user_id')->sort()->values()->all(),
                    'business_owner_ids' => [],
                    'business_ids' => [],
                    'permit_application_ids' => [],
                    'semantic_classification' => 'synthetic_only',
                    'production_liability' => false,
                ],
            ]);
        }, 3);
    }
}
