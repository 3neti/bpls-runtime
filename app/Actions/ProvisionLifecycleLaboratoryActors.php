<?php

namespace App\Actions;

use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAccessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProvisionLifecycleLaboratoryActors
{
    public function __construct(
        private readonly EnsureBplsInstitution $ensureInstitution,
        private readonly LifecycleCleanroomDefinition $definition,
    ) {}

    /** @return array<string, User> */
    public function handle(?User $performedBy = null, string $reason = 'Install the canonical lifecycle laboratory actors.'): array
    {
        if (app()->isProduction() || config('bpls_installation.seed_laboratory_actors') !== true) {
            throw new RuntimeException('Lifecycle laboratory actor provisioning is disabled in this environment.');
        }

        $password = config('bpls_installation.laboratory_actor_password');
        if (! is_string($password) || mb_strlen($password) < 8) {
            throw new RuntimeException('The lifecycle laboratory actor password must contain at least eight characters.');
        }

        $this->ensureInstitution->handle();

        return DB::transaction(function () use ($performedBy, $reason, $password): array {
            $users = [];

            foreach ($this->definitions() as $key => $definition) {
                $role = Role::query()->where('code', $definition['role_code'])->sole();
                $user = User::query()->firstOrNew(['email' => $definition['email']]);
                $before = $user->exists ? $this->state($user) : null;

                $user->forceFill([
                    'name' => $definition['name'],
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'access_status' => 'active',
                    'access_expires_at' => null,
                ]);
                if (! $user->exists) {
                    $user->password = Hash::make($password);
                }
                $user->save();
                $user->syncRoles([$role]);
                $user->load('roles.permissions');

                if ($performedBy instanceof User) {
                    UserAccessAudit::query()->create([
                        'actor_user_id' => $performedBy->id,
                        'subject_user_id' => $user->id,
                        'action' => 'laboratory_actor_provisioned',
                        'reason' => $reason,
                        'before_state' => $before,
                        'after_state' => $this->state($user),
                    ]);
                }

                $users[$key] = $user;
            }

            return $users;
        }, 3);
    }

    /** @return array<string, array{name: string, email: string, role_code: string}> */
    public function definitions(): array
    {
        $roleCodes = [
            'citizen' => 'citizen',
            'intake' => 'bplo',
            'assessment_officer' => 'assessment_officer',
            'assessor' => 'assessor',
            'engineering' => 'engineering',
            'health' => 'health',
            'menro' => 'menro',
            'treasury' => 'treasury',
            'municipal_treasurer' => 'municipal_treasurer',
            'cashier' => 'cashier',
            'permit_issuer' => 'mayor_office',
            'releasing_officer' => 'releasing',
        ];

        return collect($this->definition->actors())->mapWithKeys(
            fn (array $actor, string $key): array => [$key => [
                'name' => $actor['label'],
                'email' => str($key)->replace('_', '-')->append('@bpls-runtime.test')->toString(),
                'role_code' => $roleCodes[$key],
            ]],
        )->all();
    }

    /** @return array<string, mixed> */
    private function state(User $user): array
    {
        return [
            'email' => $user->email,
            'access_status' => $user->access_status,
            'access_expires_at' => $user->access_expires_at?->toIso8601String(),
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }
}
