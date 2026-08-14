<?php

namespace App\Actions;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;

class EnsureCitizenRole
{
    public function handle(): Role
    {
        $permissions = collect([
            UserPermission::AccessCitizen,
            UserPermission::CreateOwnPermitApplications,
            UserPermission::EditOwnPermitApplications,
            UserPermission::ViewOwnPermitApplications,
        ])->map(fn (UserPermission $permission): Permission => Permission::query()->firstOrCreate(
            ['code' => $permission->value],
            [
                'name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                'description' => null,
            ],
        ));

        $citizenRole = Role::query()->firstOrCreate(
            ['code' => UserRole::Citizen->value],
            [
                'name' => 'Citizen',
                'description' => 'Authenticated citizen permit applicant.',
            ],
        );
        $citizenRole->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        return $citizenRole;
    }
}
