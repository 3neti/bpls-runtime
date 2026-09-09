<?php

namespace App\Actions;

use App\Enums\FeeDeterminationChannel;
use App\Enums\UserPermission;
use App\Models\BusinessDivision;
use App\Models\FeeRule;
use App\Models\LineOfBusiness;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use RuntimeException;

class InspectInstallationReadiness
{
    public function __construct(
        private readonly EnsureBplsInstitution $institution,
        private readonly ProvisionLifecycleLaboratoryActors $laboratoryActors,
    ) {}

    /** @return array<string, mixed> */
    public function handle(bool $failOnMissing = true): array
    {
        $expectedRoles = array_keys($this->institution->roleDefinitions());
        $expectedActors = collect($this->laboratoryActors->definitions())->pluck('email');
        $laboratoryExpected = config('bpls_installation.seed_laboratory_actors') === true && ! app()->isProduction();
        $checks = [
            'canonical_permissions' => Permission::query()->whereIn('code', array_column(UserPermission::cases(), 'value'))->count() === count(UserPermission::cases()),
            'institutional_roles' => Role::query()->whereIn('code', $expectedRoles)->count() === count($expectedRoles),
            'active_lines_of_business' => LineOfBusiness::query()->where('is_active', true)->exists(),
            'business_divisions' => BusinessDivision::query()->exists(),
            'concerned_office_payment_orders' => FeeRule::query()->where('determination_channel', FeeDeterminationChannel::ConcernedOfficePaymentOrder)->where('is_active', true)->exists(),
            'treasury_lob_payment_items' => FeeRule::query()->where('determination_channel', FeeDeterminationChannel::TreasuryLineOfBusiness)->where('is_active', true)->exists(),
            'concerned_office_reference' => count(config('ipil_references.concerned_offices.items', [])) > 0,
            'municipality_identity' => filled(config('municipality.name')),
            'receipt_configuration' => config('municipality.official_receipt.form.accountable_form_number') === 51,
            'laboratory_actors' => ! $laboratoryExpected || User::query()->whereIn('email', $expectedActors)->count() === $expectedActors->count(),
        ];
        $failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));

        if ($failOnMissing && $failed !== []) {
            throw new RuntimeException('BPLS installation is not workflow-ready: '.implode(', ', $failed));
        }

        return [
            'pass' => $failed === [],
            'checks' => $checks,
            'failed' => $failed,
            'counts' => [
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
                'users' => User::query()->count(),
                'laboratory_actors' => User::query()->whereIn('email', $expectedActors)->count(),
                'lines_of_business' => LineOfBusiness::query()->where('is_active', true)->count(),
                'fee_rules' => FeeRule::query()->where('is_active', true)->count(),
            ],
        ];
    }
}
