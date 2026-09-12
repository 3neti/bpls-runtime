<?php

namespace App\Actions\Fortify;

use App\Actions\ClaimClassicLifecycleRegistration;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly ClaimClassicLifecycleRegistration $claimClassicRegistration,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'classic_cleanroom_invitation' => ['nullable', 'string', 'size:64'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $citizenRole = $this->registrationRole();
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->assignRole($citizenRole);
            if (is_string($input['classic_cleanroom_invitation'] ?? null)) {
                $this->claimClassicRegistration->handle($user, $input['classic_cleanroom_invitation']);
            }

            return $user;
        }, 3);
    }

    private function registrationRole(): Role
    {
        $role = Role::query()
            ->where('code', UserRole::Citizen->value)
            ->where('name', UserRole::Citizen->value)
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();
        $requiredPermissions = [
            UserPermission::AccessCitizen->value,
            UserPermission::CreateOwnPermitApplications->value,
            UserPermission::EditOwnPermitApplications->value,
            UserPermission::SubmitOwnPermitApplications->value,
            UserPermission::UploadOwnPermitApplicationDocuments->value,
            UserPermission::ViewOwnPermitApplications->value,
            UserPermission::ViewOwnPermitApplicationDocuments->value,
            UserPermission::ViewOwnPermitApplicationFinancials->value,
        ];
        $assignedPermissions = $role?->permissions()
            ->where('guard_name', 'web')
            ->whereColumn('permissions.name', 'permissions.code')
            ->pluck('code')->all() ?? [];

        if (! $role instanceof Role || array_diff($requiredPermissions, $assignedPermissions) !== []) {
            throw ValidationException::withMessages([
                'email' => 'Citizen registration is temporarily unavailable. Please contact the BPLS administrator.',
            ]);
        }

        return $role;
    }
}
