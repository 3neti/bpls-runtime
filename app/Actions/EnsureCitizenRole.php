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
            UserPermission::SubmitOwnPermitApplications,
            UserPermission::UploadOwnPermitApplicationDocuments,
            UserPermission::ViewOwnPermitApplications,
            UserPermission::ViewOwnPermitApplicationDocuments,
            UserPermission::ViewOwnPermitApplicationFinancials,
        ])->map(fn (UserPermission $permission): Permission => Permission::query()->firstOrCreate(
            ['name' => $permission->value, 'guard_name' => 'web'],
            [
                'code' => $permission->value,
                'display_name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                'description' => null,
            ],
        ));

        $citizenRole = Role::query()->firstOrCreate(
            ['name' => UserRole::Citizen->value, 'guard_name' => 'web'],
            [
                'code' => UserRole::Citizen->value,
                'display_name' => 'Citizen',
                'description' => 'Authenticated citizen permit applicant.',
            ],
        );
        $citizenRole->syncPermissions($permissions);

        return $citizenRole;
    }
}
