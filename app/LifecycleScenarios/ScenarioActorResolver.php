<?php

namespace App\LifecycleScenarios;

use App\Enums\UserPermission;
use App\Models\User;
use RuntimeException;

final class ScenarioActorResolver
{
    /**
     * @return array<string, User>
     */
    public function resolve(LifecycleScenarioDefinition $scenario): array
    {
        return collect($scenario->actors)
            ->mapWithKeys(fn (string $identity, string $role): array => [$role => $this->resolveActor($identity, $role)])
            ->all();
    }

    private function resolveActor(string $identity, string $role): User
    {
        $email = config('lifecycle_scenarios.actors.'.$identity.'.email');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException("Lifecycle scenario actor [{$identity}] for role [{$role}] is not configured.");
        }

        $user = User::query()
            ->with('role.permissions')
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            throw new RuntimeException("Lifecycle scenario actor [{$identity}] with email [{$email}] was not found. Create or seed the user in local/testing before running this scenario.");
        }

        foreach ($this->requiredPermissions($identity) as $permission) {
            if (! $user->can($permission->value)) {
                throw new RuntimeException("Lifecycle scenario actor [{$identity}] is missing permission [{$permission->value}].");
            }
        }

        return $user;
    }

    /**
     * @return list<UserPermission>
     */
    private function requiredPermissions(string $identity): array
    {
        return match ($identity) {
            'primary_operator' => [
                UserPermission::AccessStaff,
                UserPermission::ViewPermitApplications,
                UserPermission::CreatePermitApplications,
                UserPermission::AssessPermitApplications,
                UserPermission::UpdatePermitApplicationStatus,
                UserPermission::ManageStoryboards,
            ],
            default => [
                UserPermission::AccessStaff,
            ],
        };
    }
}
