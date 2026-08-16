<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\User;

class BuildUserDirectory
{
    /** @return array<string, mixed> */
    public function handle(string $search = '', ?string $roleCode = null): array
    {
        $users = User::query()
            ->with(['role:id,name,code', 'businessOwner:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhereHas('role', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('businessOwner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($roleCode === 'unassigned', fn ($query) => $query->whereNull('role_id'))
            ->when($roleCode !== null && $roleCode !== 'unassigned', function ($query) use ($roleCode): void {
                $query->whereHas('role', fn ($query) => $query->where('code', $roleCode));
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
                'role' => $user->role === null ? null : [
                    'name' => $user->role->name,
                    'code' => $user->role->code,
                ],
                'business_owner' => $user->businessOwner === null ? null : [
                    'id' => $user->businessOwner->id,
                    'name' => $user->businessOwner->name,
                ],
            ]);

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Role $role): array => [
                'label' => $role->name,
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
            'summary' => [
                'user_count' => User::query()->count(),
                'verified_user_count' => User::query()->whereNotNull('email_verified_at')->count(),
                'linked_owner_count' => User::query()->whereNotNull('business_owner_id')->count(),
                'unassigned_role_count' => User::query()->whereNull('role_id')->count(),
                'role_distribution' => $roles
                    ->mapWithKeys(fn (array $role): array => [$role['value'] => $role['user_count']])
                    ->all(),
            ],
        ];
    }
}
