<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ProvisionStakeholderPreviewPersonas
{
    public function __construct(private readonly StakeholderPreviewSafety $previewSafety) {}

    /** @return array<string, User> */
    public function handle(?string $password = null): array
    {
        if (config('stakeholder_preview.mode') !== true) {
            return [];
        }

        if (! $this->previewSafety->isEnabled()) {
            throw new RuntimeException('Stakeholder Preview Mode is enabled, but its canonical synthetic-only safety configuration is incomplete.');
        }

        return DB::transaction(function () use ($password): array {
            return collect(StakeholderPreviewPersona::cases())
                ->mapWithKeys(function (StakeholderPreviewPersona $persona) use ($password): array {
                    $role = $this->previewRole($persona);
                    $user = User::query()->firstOrNew(['email' => $persona->approvedEmail()]);

                    $user->forceFill([
                        'name' => $persona->accountName(),
                        'role_id' => $role->id,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ]);

                    if (! $user->exists) {
                        $user->password = Hash::make($this->initialPassword($password));
                    }

                    if (! $user->exists || $user->isDirty()) {
                        $user->save();
                    }

                    return [$persona->value => $user->refresh()->load('role.permissions')];
                })
                ->all();
        });
    }

    private function previewRole(StakeholderPreviewPersona $persona): Role
    {
        $role = Role::query()->updateOrCreate(
            ['code' => $persona->roleCode()],
            [
                'name' => 'Preview '.$persona->label(),
                'description' => 'Synthetic Stakeholder Preview access infrastructure; not a named municipal official, authority-position assignment, production commissioning, or scenario actor.',
            ],
        );
        $permissionIds = collect($persona->permissions())->map(function (UserPermission $permission): int {
            return Permission::query()->firstOrCreate(
                ['code' => $permission->value],
                [
                    'name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(),
                    'description' => null,
                ],
            )->id;
        });
        $role->permissions()->sync($permissionIds->all());

        return $role;
    }

    private function initialPassword(?string $password): string
    {
        if (is_string($password) && mb_strlen($password) >= 16) {
            return $password;
        }

        return Str::random(64);
    }
}
