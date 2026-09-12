<?php

namespace App\Http\Middleware;

use App\Models\LifecycleCleanroomRun;
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
        $showEngineeringControls = $previewPersona !== null;
        $cleanroomActor = $showEngineeringControls ? $this->cleanroomActor($user, $previewSafety) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user?->primaryRole()?->code,
                'roles' => $user?->roleCodes() ?? [],
                'can_access_staff' => $user?->can('staff.access') ?? false,
                'can_access_citizen' => $user?->can('citizen.access') ?? false,
                'can_view_permit_applications' => $user?->can('permit_applications.view') ?? false,
                'can_assess_permit_applications' => $user?->can('permit_applications.assess') ?? false,
                'can_approve_assessments' => $user?->can('assessments.approve') ?? false,
                'can_counter_check_business_permit_evaluations' => $user?->can('business_permit_evaluations.counter_check') ?? false,
                'can_view_payment_schedules' => $user?->can('payment_schedules.view') ?? false,
                'can_prepare_payment_schedules' => $user?->can('payment_schedules.prepare') ?? false,
                'can_view_collections' => $user?->can('collections.view') ?? false,
                'can_record_collections' => $user?->can('collections.record') ?? false,
                'can_view_receipts' => $user?->can('receipts.view') ?? false,
                'can_issue_receipts' => $user?->can('receipts.issue') ?? false,
                'can_view_billing_groups' => $user?->can('billing_groups.view') ?? false,
                'can_view_reports' => $user?->can('reports.view') ?? false,
                'can_view_fee_rules' => $user?->can('fee_rules.view') ?? false,
                'can_view_fee_matrix' => $user?->canAny([
                    'fee_rules.view',
                    'business_permit_evaluations.contribute',
                ]) ?? false,
                'can_manage_fee_rules' => $user?->can('fee_rules.manage') ?? false,
                'can_view_users' => $user?->can('users.view') ?? false,
                'can_provision_users' => $user?->can('users.provision') ?? false,
                'can_manage_user_access' => $user?->can('users.access.manage') ?? false,
                'can_provision_laboratory_actors' => $user?->can('laboratory.actors.provision') ?? false,
                'can_view_roles' => $user?->can('roles.view') ?? false,
                'can_view_municipality_configuration' => $user?->can('municipality_configuration.view') ?? false,
            ],
            'stakeholder_preview' => $previewSafety->isEnabled() ? [
                'enabled' => true,
                'show_engineering_controls' => $showEngineeringControls,
                'authorized_legacy_review' => $previewSafety->allowsAuthorizedLegacySpecimens(),
                'access' => $previewSafety->requiresPrivateAuthentication() ? 'private' : 'open',
                'current_persona' => $previewPersona?->value,
                'current_label' => $previewPersona?->label(),
                'cleanroom_actor' => $cleanroomActor,
                'personas' => $showEngineeringControls ? $previewSafety->personas() : [],
                'what_to_try' => $previewSafety->guidanceFor($user),
                'recovery_message' => 'Preview data can be restored by the preview administrator.',
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** @return array{run_id: int, public_id: string, key: string, label: string, laboratory_url: string}|null */
    private function cleanroomActor(?User $user, StakeholderPreviewSafety $safety): ?array
    {
        if (! $safety->isEnabled() || ! $user instanceof User) {
            return null;
        }

        foreach (LifecycleCleanroomRun::query()->where('status', 'active')->latest('id')->limit(10)->get() as $run) {
            foreach (data_get($run->actor_manifest, 'actors', []) as $key => $actor) {
                if (($actor['user_id'] ?? null) === $user->id && $user->roles->contains('id', $actor['role_id'] ?? null)) {
                    return [
                        'run_id' => $run->id,
                        'public_id' => $run->public_id,
                        'key' => $key,
                        'label' => $actor['label'],
                        'laboratory_url' => route('stakeholder-preview.lifecycle-laboratory.cleanrooms.status', $run, false),
                    ];
                }
            }
        }

        return null;
    }
}
