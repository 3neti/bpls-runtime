<?php

namespace App\Actions;

use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\InstitutionalPosition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class EnsureBplsInstitution
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $permissions = collect(UserPermission::cases())
                ->mapWithKeys(fn (UserPermission $permission): array => [
                    $permission->value => Permission::query()->updateOrCreate(
                        ['code' => $permission->value],
                        ['name' => str($permission->value)->replace(['.', '_'], ' ')->title()->toString(), 'description' => null],
                    ),
                ]);

            $roles = collect($this->roleDefinitions())
                ->mapWithKeys(function (array $definition, string $code) use ($permissions): array {
                    $role = Role::query()->updateOrCreate(
                        ['code' => $code],
                        ['name' => $definition['name'], 'description' => $definition['description']],
                    );
                    $role->permissions()->sync(
                        $permissions->only($definition['permissions'])->pluck('id')->all(),
                    );

                    return [$code => $role];
                });

            foreach ($this->positionDefinitions() as $code => $definition) {
                InstitutionalPosition::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $definition['name'],
                        'capability_role_id' => $roles[$definition['role_code']]->id,
                        'authority_classification' => $definition['authority_classification'],
                        'assignment_status' => 'unassigned',
                        'metadata' => [
                            'named_official_required_for_production' => $definition['named_official_required_for_production'],
                            'production_commissioned' => false,
                            'position_is_not_permission' => true,
                        ],
                    ],
                );
            }

            return [
                'permission_count' => $permissions->count(),
                'role_count' => $roles->count(),
                'position_count' => count($this->positionDefinitions()),
                'commissioning_administrator' => $this->ensureCommissioningAdministrator($roles[UserRole::Admin->value]),
            ];
        });
    }

    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    public function roleDefinitions(): array
    {
        $definitions = [
            UserRole::Admin->value => [
                'name' => 'BPLS Super User',
                'description' => 'Commissioning and recovery capability envelope; not a municipal office or production authority assignment.',
                'permissions' => array_column(UserPermission::cases(), 'value'),
            ],
        ];

        foreach (StakeholderPreviewPersona::cases() as $persona) {
            $definitions[$this->institutionalRoleCode($persona)] = [
                'name' => $persona->label(),
                'description' => $persona->description(),
                'permissions' => array_map(
                    fn (UserPermission $permission): string => $permission->value,
                    $persona->permissions(),
                ),
            ];
        }

        return $definitions;
    }

    /** @return array<string, array{name: string, role_code: string, authority_classification: string, named_official_required_for_production: bool}> */
    public function positionDefinitions(): array
    {
        return [
            'bplo_officer' => $this->position('BPLO Officer', 'bplo', 'licensing_operations'),
            'assessment_officer' => $this->position('Assessment Officer', 'assessment_officer', 'assessment_preparation'),
            'treasury_counter_checker' => $this->position('Treasury Counter-checker', 'treasury', 'treasury_counter_check'),
            'municipal_treasurer' => $this->position('Municipal Treasurer', 'municipal_treasurer', 'statutory_assessment_approval', true),
            'cashier' => $this->position('Cashier', 'cashier', 'collection_and_receipt'),
            'municipal_management' => $this->position('Municipal Management', 'management', 'management_oversight'),
            'municipal_engineer' => $this->position('Municipal Engineering Officer', 'engineering', 'concerned_office_evaluation'),
            'mpdo_mpdc' => $this->position('MPDO / MPDC', 'mpdo', 'concerned_office_evaluation'),
            'municipal_assessor' => $this->position('Municipal Assessor', 'assessor', 'concerned_office_evaluation'),
            'municipal_health_officer' => $this->position('Municipal Health Officer', 'health', 'concerned_office_evaluation'),
            'menro_officer' => $this->position('MENRO Officer', 'menro', 'concerned_office_evaluation'),
            'mayors_office_reviewer' => $this->position("Mayor's Office Reviewer", 'mayor_office', 'executive_review_uncommissioned', true),
            'releasing_officer' => $this->position('Releasing Officer', 'releasing', 'permit_release_uncommissioned', true),
        ];
    }

    private function institutionalRoleCode(StakeholderPreviewPersona $persona): string
    {
        return match ($persona) {
            StakeholderPreviewPersona::Citizen => UserRole::Citizen->value,
            StakeholderPreviewPersona::Bplo => UserRole::Bplo->value,
            StakeholderPreviewPersona::Treasury => UserRole::Treasury->value,
            default => $persona->value,
        };
    }

    /** @return array{name: string, role_code: string, authority_classification: string, named_official_required_for_production: bool} */
    private function position(
        string $name,
        string $roleCode,
        string $authorityClassification,
        bool $namedOfficialRequiredForProduction = false,
    ): array {
        return [
            'name' => $name,
            'role_code' => $roleCode,
            'authority_classification' => $authorityClassification,
            'named_official_required_for_production' => $namedOfficialRequiredForProduction,
        ];
    }

    /** @return array{status: string, email: string|null, user_id: int|null} */
    private function ensureCommissioningAdministrator(Role $adminRole): array
    {
        $email = config('bpls_installation.commissioning_administrator.email');

        if (! is_string($email) || blank($email)) {
            return ['status' => 'external_link_required', 'email' => null, 'user_id' => null];
        }

        $email = Str::lower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('BPLS_COMMISSIONING_ADMIN_EMAIL must be a valid email address.');
        }

        $user = User::query()->where('email', $email)->first();
        if ($user instanceof User && $user->role_id !== $adminRole->id) {
            throw new RuntimeException("Commissioning administrator email [{$email}] belongs to a non-administrative account.");
        }

        if (! $user instanceof User) {
            $user = User::query()->create([
                'role_id' => $adminRole->id,
                'name' => (string) config('bpls_installation.commissioning_administrator.name'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);
        }

        return ['status' => 'linked_password_reset_required', 'email' => $email, 'user_id' => $user->id];
    }
}
