<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;

class BuildRolePermissionMatrix
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        $storedPermissions = Permission::query()
            ->orderBy('code')
            ->get()
            ->keyBy('code');
        $canonicalPermissions = collect(UserPermission::cases());
        $canonicalCodes = $canonicalPermissions->map(fn (UserPermission $permission): string => $permission->value);

        $roles = Role::query()
            ->with(['permissions:id,code', 'users:id,role_id'])
            ->orderByRaw("case code when 'admin' then 1 when 'bplo' then 2 when 'treasury' then 3 when 'citizen' then 4 else 5 end")
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($canonicalPermissions): array {
                $assignedCodes = $role->permissions->pluck('code');
                $hasAdminOverride = $role->code === UserRole::Admin->value;

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'description' => $role->description,
                    'user_count' => $role->users->count(),
                    'is_system' => in_array($role->code, array_column(UserRole::cases(), 'value'), true),
                    'access_mode' => $hasAdminOverride ? 'admin_override' : 'assigned_permissions',
                    'assigned_permission_count' => $assignedCodes->intersect($canonicalPermissions->pluck('value'))->count(),
                    'effective_permission_count' => $hasAdminOverride
                        ? $canonicalPermissions->count()
                        : $assignedCodes->intersect($canonicalPermissions->pluck('value'))->count(),
                    'permissions' => $canonicalPermissions
                        ->map(fn (UserPermission $permission): array => [
                            'code' => $permission->value,
                            'assigned' => $assignedCodes->contains($permission->value),
                            'effective' => $hasAdminOverride || $assignedCodes->contains($permission->value),
                            'source' => $hasAdminOverride
                                ? 'admin_override'
                                : ($assignedCodes->contains($permission->value) ? 'assigned' : 'none'),
                        ])
                        ->values()
                        ->all(),
                    'unknown_assigned_permission_codes' => $assignedCodes
                        ->diff($canonicalPermissions->pluck('value'))
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $permissions = $canonicalPermissions
            ->map(function (UserPermission $permission) use ($storedPermissions): array {
                $isStored = $storedPermissions->has($permission->value);
                $stored = $storedPermissions->get($permission->value);

                return [
                    'code' => $permission->value,
                    'name' => $isStored
                        ? $stored->name
                        : str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                    'description' => $isStored ? $stored->description : null,
                    'area' => str($permission->value)->before('.')->replace('_', ' ')->title()->toString(),
                    'catalog_status' => $isStored ? 'stored' : 'missing',
                ];
            })
            ->values();
        $missingCodes = $canonicalCodes->diff($storedPermissions->keys())->values();
        $unknownCodes = $storedPermissions->keys()->diff($canonicalCodes)->values();

        return [
            'summary' => [
                'role_count' => $roles->count(),
                'assigned_user_count' => $roles->sum('user_count'),
                'canonical_permission_count' => $canonicalPermissions->count(),
                'stored_permission_count' => $storedPermissions->count(),
                'missing_permission_count' => $missingCodes->count(),
                'unknown_permission_count' => $unknownCodes->count(),
                'catalog_in_sync' => $missingCodes->isEmpty() && $unknownCodes->isEmpty(),
            ],
            'roles' => $roles->all(),
            'permissions' => $permissions->all(),
            'catalog_drift' => [
                'missing_permission_codes' => $missingCodes->all(),
                'unknown_permission_codes' => $unknownCodes->all(),
            ],
        ];
    }
}
