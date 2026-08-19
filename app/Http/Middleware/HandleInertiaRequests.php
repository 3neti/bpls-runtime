<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $authenticatedUser = $request->user();
        $user = $authenticatedUser instanceof User ? $authenticatedUser : null;

        $previewSafety = app(StakeholderPreviewSafety::class);
        $previewPersona = $previewSafety->personaFor($user);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user?->role?->code,
                'can_access_staff' => $user?->can('staff.access') ?? false,
                'can_access_citizen' => $user?->can('citizen.access') ?? false,
                'can_view_permit_applications' => $user?->can('permit_applications.view') ?? false,
                'can_view_payment_schedules' => $user?->can('payment_schedules.view') ?? false,
                'can_view_receipts' => $user?->can('receipts.view') ?? false,
                'can_view_billing_groups' => $user?->can('billing_groups.view') ?? false,
                'can_view_reports' => $user?->can('reports.view') ?? false,
                'can_view_fee_rules' => $user?->can('fee_rules.view') ?? false,
                'can_view_users' => $user?->can('users.view') ?? false,
                'can_view_roles' => $user?->can('roles.view') ?? false,
                'can_view_municipality_configuration' => $user?->can('municipality_configuration.view') ?? false,
            ],
            'stakeholder_preview' => $previewSafety->isEnabled() ? [
                'enabled' => true,
                'current_persona' => $previewPersona?->value,
                'current_label' => $previewPersona?->label(),
                'personas' => $previewSafety->personas(),
                'what_to_try' => $previewSafety->guidanceFor($user),
                'recovery_message' => 'Preview data can be restored by the preview administrator.',
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
