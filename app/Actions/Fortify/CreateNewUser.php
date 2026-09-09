<?php

namespace App\Actions\Fortify;

use App\Actions\ClaimClassicLifecycleRegistration;
use App\Actions\EnsureCitizenRole;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly EnsureCitizenRole $ensureCitizenRole,
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
            $citizenRole = $this->ensureCitizenRole->handle();
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
}
