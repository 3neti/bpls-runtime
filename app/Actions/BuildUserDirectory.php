<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;

class BuildUserDirectory
{
    public function __construct(private readonly EnsureBplsInstitution $institution) {}

    /** @return array<string, mixed> */
    public function handle(string $search = '', ?string $roleCode = null): array
    {
        $users = User::query()
            ->with(['roles:id,name,code,display_name', 'businessOwner:id,name'])
            ->withCount('accessAudits')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('roles', fn ($query) => $query->where('display_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('businessOwner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($roleCode === 'unassigned', fn ($query) => $query->doesntHave('roles'))
            ->when($roleCode !== null && $roleCode !== 'unassigned', function ($query) use ($roleCode): void {
                $query->whereHas('roles', fn ($query) => $query->where('code', $roleCode));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'roles' => $user->roles->map(fn ($role): array => [
                    'name' => (string) $role->getAttribute('display_name'),
                    'code' => (string) $role->getAttribute('code'),
                ])->values()->all(),
                'access_status' => $user->access_status,
                'access_expires_at' => $user->access_expires_at?->toIso8601String(),
                'access_audit_count' => $user->access_audits_count,
                'business_owner' => $user->businessOwner === null ? null : [
                    'id' => $user->businessOwner->id,
                    'name' => $user->businessOwner->name,
                ],
            ]);

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('display_name')
            ->get(['id', 'name', 'code', 'display_name'])
            ->map(fn (Role $role): array => [
                'label' => $role->display_name,
                'value' => $role->code,
                'user_count' => $role->users_count,
            ])
            ->values();

        return [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'role' => $roleCode,
            ],
            'roles' => $roles,
            'assignable_roles' => $roles
                ->whereIn('value', array_keys($this->institution->roleDefinitions()))
                ->values(),
            'summary' => [
                'user_count' => User::query()->count(),
                'verified_user_count' => User::query()->whereNotNull('email_verified_at')->count(),
                'linked_owner_count' => User::query()->whereNotNull('business_owner_id')->count(),
                'unassigned_role_count' => User::query()->doesntHave('roles')->count(),
                'suspended_user_count' => User::query()->where('access_status', 'suspended')->count(),
                'role_distribution' => $roles
                    ->mapWithKeys(fn (array $role): array => [$role['value'] => $role['user_count']])
                    ->all(),
            ],
        ];
    }
}
