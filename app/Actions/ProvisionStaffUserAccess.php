<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAccessAudit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProvisionStaffUserAccess
{
    public function __construct(
        private readonly EnsureBplsInstitution $institution,
        private readonly SyncUserInstitutionalPositions $syncPositions,
    ) {}

    /** @param list<string> $roleCodes */
    public function create(User $performedBy, string $name, string $email, string $password, array $roleCodes, string $reason, ?Carbon $expiresAt = null): User
    {
        return DB::transaction(function () use ($performedBy, $name, $email, $password, $roleCodes, $reason, $expiresAt): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => str($email)->lower()->toString(),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'access_status' => 'active',
                'access_expires_at' => $expiresAt,
            ]);
            $user->syncRoles($this->roles($roleCodes));
            $this->syncPositions->handle($user, $performedBy, $reason);
            $user->load('roles');
            $this->audit($performedBy, $user, 'staff_account_provisioned', $reason, null);

            return $user;
        }, 3);
    }

    /** @param list<string> $roleCodes */
    public function update(User $performedBy, User $user, array $roleCodes, string $accessStatus, string $reason, ?Carbon $expiresAt = null): User
    {
        return DB::transaction(function () use ($performedBy, $user, $roleCodes, $accessStatus, $reason, $expiresAt): User {
            $user->load('roles');
            $before = $this->state($user);
            $roles = $this->roles($roleCodes);

            if ($performedBy->is($user) && ($accessStatus !== 'active' || ! $roles->contains('code', 'admin'))) {
                throw ValidationException::withMessages(['roles' => 'An administrator cannot suspend their own access or remove their own administrator role.']);
            }

            $user->forceFill(['access_status' => $accessStatus, 'access_expires_at' => $expiresAt])->save();
            $user->syncRoles($roles);
            $this->syncPositions->handle($user, $performedBy, $reason);
            $user->load('roles');
            $this->audit($performedBy, $user, 'staff_access_updated', $reason, $before);

            return $user;
        }, 3);
    }

    /** @param list<string> $roleCodes
     * @return Collection<int, Role>
     */
    private function roles(array $roleCodes): Collection
    {
        $codes = array_values(array_unique($roleCodes));
        $assignableCodes = array_keys($this->institution->roleDefinitions());
        $roles = Role::query()->whereIn('code', $codes)->whereIn('code', $assignableCodes)->get();
        if ($roles->count() !== count($codes)) {
            throw ValidationException::withMessages(['roles' => 'One or more selected roles are unavailable.']);
        }

        return $roles;
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $performedBy, User $user, string $action, string $reason, ?array $before): void
    {
        UserAccessAudit::query()->create([
            'actor_user_id' => $performedBy->id,
            'subject_user_id' => $user->id,
            'action' => $action,
            'reason' => $reason,
            'before_state' => $before,
            'after_state' => $this->state($user),
        ]);
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
