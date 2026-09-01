<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use App\StakeholderPreview\StakeholderPreviewSafety;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateLifecycleCleanroomActor
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    /** @return list<array{key: string, label: string}> */
    public function entries(LifecycleCleanroomRun $run): array
    {
        return array_values(collect($run->actors())
            ->map(fn (array $actor, string $key): array => ['key' => $key, 'label' => $actor['label']])
            ->all());
    }

    public function handle(Request $request, LifecycleCleanroomRun $run, string $actorKey, ?string $destination = null): string
    {
        $this->safety->ensureReady();
        $actor = $run->actor($actorKey);
        abort_unless(
            $run->status === 'active'
            && is_array($actor)
            && data_get($run->actor_manifest, 'semantic_classification') === 'synthetic_only'
            && data_get($run->actor_manifest, 'production_liability') === false,
            404,
        );
        $user = User::query()
            ->with('role.permissions')
            ->whereKey($actor['user_id'])
            ->where('role_id', $actor['role_id'])
            ->whereHas('role', fn ($query) => $query->where('code', 'lifecycle-cleanroom-'.$actorKey))
            ->first();
        abort_unless($user instanceof User, 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $authenticatedUser = Auth::guard('web')->loginUsingId($user->getAuthIdentifier());
        $request->session()->regenerate();
        abort_unless($authenticatedUser instanceof User, 404);

        $resolvedDestination = $destination ?? $this->defaultDestination($run, $actorKey);
        if ($resolvedDestination === 'citizen.permit-applications.create') {
            $request->session()->put('lifecycle_cleanroom_intake_run_id', $run->id);
        }

        return route($resolvedDestination, $this->destinationParameter($run, $resolvedDestination), absolute: false);
    }

    private function defaultDestination(LifecycleCleanroomRun $run, string $actorKey): string
    {
        if ($actorKey === 'citizen' && $run->new_application_id === null) {
            return 'citizen.permit-applications.create';
        }

        if ($actorKey === 'citizen') {
            return 'citizen.permit-applications.show';
        }

        if (in_array($actorKey, ['assessor', 'engineering', 'health', 'menro', 'treasury'], true)) {
            return 'staff.permit-applications.evaluation.show';
        }

        if ($actorKey === 'municipal_treasurer') {
            return 'staff.permit-applications.assessments.show';
        }

        return 'staff.permit-applications.show';
    }

    /** @return int|array{} */
    private function destinationParameter(LifecycleCleanroomRun $run, string $destination): int|array
    {
        if ($destination === 'citizen.permit-applications.create') {
            return [];
        }

        $application = $this->currentApplication($run);
        if ($destination === 'staff.permit-applications.assessments.show') {
            return $application->assessments()->whereNull('superseded_at')->sole()->id;
        }

        return $application->id;
    }

    private function currentApplication(LifecycleCleanroomRun $run): PermitApplication
    {
        $applicationId = $run->renewal_application_id ?? $run->new_application_id;
        abort_unless(is_int($applicationId), 404);

        return PermitApplication::query()->findOrFail($applicationId);
    }
}
