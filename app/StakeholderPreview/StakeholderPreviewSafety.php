<?php

namespace App\StakeholderPreview;

use App\Enums\StakeholderPreviewPersona;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StakeholderPreviewSafety
{
    public const string Profile = 'stakeholder_preview_cycle_4';

    public function isEnabled(): bool
    {
        if (app()->isProduction() || in_array(strtolower(app()->environment()), ['prod', 'production'], true)) {
            return false;
        }

        if (config('stakeholder_preview.mode') !== true
            || config('stakeholder_preview.profile') !== self::Profile
            || config('stakeholder_preview.data_classification') !== 'synthetic_only'
            || config('stakeholder_preview.pii_mode') !== 'synthetic_only'
            || config('stakeholder_preview.production_migration_enabled') !== false
            || config('stakeholder_preview.production_integrations') !== 'disabled') {
            return false;
        }

        foreach (StakeholderPreviewPersona::cases() as $persona) {
            if (config('stakeholder_preview.accounts.'.$persona->value) !== $persona->approvedEmail()) {
                return false;
            }
        }

        return true;
    }

    public function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new NotFoundHttpException;
        }
    }

    public function ensureReady(): void
    {
        $this->ensureEnabled();

        foreach (StakeholderPreviewPersona::cases() as $persona) {
            $this->account($persona);
        }
    }

    public function account(StakeholderPreviewPersona $persona): User
    {
        $this->ensureEnabled();

        $user = User::query()
            ->with('role.permissions')
            ->where('email', $persona->approvedEmail())
            ->first();

        if (! $user instanceof User || ! $this->matchesPersona($user, $persona)) {
            throw new NotFoundHttpException;
        }

        return $user;
    }

    public function personaFor(?User $user): ?StakeholderPreviewPersona
    {
        if (! $this->isEnabled() || ! $user instanceof User) {
            return null;
        }

        foreach (StakeholderPreviewPersona::cases() as $persona) {
            if ($user->email === $persona->approvedEmail() && $this->matchesPersona($user, $persona)) {
                return $persona;
            }
        }

        return null;
    }

    /** @return list<array{key: string, label: string, description: string}> */
    public function personas(): array
    {
        return array_map(fn (StakeholderPreviewPersona $persona): array => [
            'key' => $persona->value,
            'label' => $persona->label(),
            'description' => $persona->description(),
        ], StakeholderPreviewPersona::cases());
    }

    /** @return list<array{label: string, href: string}> */
    public function guidanceFor(?User $user): array
    {
        $persona = $this->personaFor($user);

        if (! $persona instanceof StakeholderPreviewPersona) {
            return [];
        }

        $items = match ($persona) {
            StakeholderPreviewPersona::Citizen => [
                ['label' => 'Open My Permit Applications', 'route' => 'citizen.permit-applications.index', 'permission' => 'citizen.permit_applications.view'],
                ['label' => 'Start a permit application', 'route' => 'citizen.permit-applications.create', 'permission' => 'citizen.permit_applications.create'],
                ['label' => 'Read account notices', 'route' => 'citizen.notifications.index', 'permission' => 'citizen.access'],
            ],
            StakeholderPreviewPersona::Bplo => [
                ['label' => 'Open All Applications', 'route' => 'staff.permit-applications.index', 'permission' => 'permit_applications.view'],
                ['label' => 'Review Assessment Work', 'route' => 'staff.permit-applications.assessments.index', 'permission' => 'permit_applications.assess'],
                ['label' => 'Inspect Taxes & Fees', 'route' => 'staff.fee-rules.index', 'permission' => 'fee_rules.view'],
            ],
            StakeholderPreviewPersona::Treasury => [
                ['label' => 'Open Payment Schedules', 'route' => 'staff.payment-schedules.index', 'permission' => 'payment_schedules.view'],
                ['label' => 'Inspect Receipts', 'route' => 'staff.receipts.index', 'permission' => 'receipts.view'],
                ['label' => 'Open Daily Collections', 'route' => 'staff.reports.daily-collections.index', 'permission' => 'reports.view'],
                ['label' => 'Open Revenue Sources', 'route' => 'staff.reports.revenue-sources.index', 'permission' => 'reports.view'],
            ],
            StakeholderPreviewPersona::Management => [
                ['label' => 'Open the Report Catalog', 'route' => 'staff.reports.index', 'permission' => 'reports.view'],
                ['label' => 'Inspect Users', 'route' => 'staff.users.index', 'permission' => 'users.view'],
                ['label' => 'Inspect Roles & Permissions', 'route' => 'staff.roles.index', 'permission' => 'roles.view'],
                ['label' => 'Review Municipality & Officials', 'route' => 'staff.municipality-configuration.index', 'permission' => 'municipality_configuration.view'],
                ['label' => 'Inspect Billing Groups', 'route' => 'staff.billing-groups.index', 'permission' => 'billing_groups.view'],
            ],
        };

        return array_values(collect($items)
            ->filter(fn (array $item): bool => Route::has($item['route']) && $user->can($item['permission']))
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'href' => route($item['route'], absolute: false),
            ])
            ->values()
            ->all());
    }

    private function matchesPersona(User $user, StakeholderPreviewPersona $persona): bool
    {
        $actualPermissions = $user->role?->permissions->pluck('code')->sort()->values()->all() ?? [];
        $expectedPermissions = collect($persona->permissions())->map->value->sort()->values()->all();

        return $user->email === $persona->approvedEmail()
            && $user->name === $persona->accountName()
            && $user->email_verified_at !== null
            && $user->role?->code === $persona->roleCode()
            && $user->two_factor_secret === null
            && $user->two_factor_recovery_codes === null
            && $user->two_factor_confirmed_at === null
            && $actualPermissions === $expectedPermissions;
    }
}
