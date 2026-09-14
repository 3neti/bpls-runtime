<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\IssueSyntheticLifecyclePermit;
use App\Actions\OrdinaryUatPermitAuthority;
use App\Actions\ProjectPermitReadiness;
use App\Actions\RecordOrdinaryUatMayoralAuthorization;
use App\Actions\ReleaseSyntheticLifecyclePermit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreOrdinaryUatPermitRequest;
use App\Models\PermitApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrdinaryUatPermitController extends Controller
{
    public function show(Request $request, PermitApplication $permitApplication, OrdinaryUatPermitAuthority $authority, ProjectPermitReadiness $project, DescribePermitVerificationBoundary $verification): Response
    {
        abort_unless($authority->allows($permitApplication, $request->user()) || $authority->allows($permitApplication, $request->user(), 'releasing'), 403);
        $readiness = $project->handle($permitApplication);
        $completion = $permitApplication->provisionalUatPermitCompletion;
        $mayor = $authority->allows($permitApplication, $request->user());

        return Inertia::render('ordinary-uat-permit/Show', [
            'application' => ['id' => $permitApplication->id, 'tracking_reference' => $permitApplication->tracking_reference],
            'readiness' => $readiness,
            'completion' => $completion === null ? null : [
                'authorized_at' => $completion->decided_at?->toIso8601String(),
                'authorization_fingerprint' => data_get($completion->source_snapshot, 'ordinary_mayoral_authorization.fingerprint'),
                'issued_at' => $completion->issued_at?->toIso8601String(), 'released_at' => $completion->released_at?->toIso8601String(),
                'permit_number' => $completion->permit_number,
            ],
            'action' => $mayor && $completion === null && $authority->prerequisites($permitApplication) ? 'authorize'
                : ($mayor && $completion?->issued_at === null && $readiness['ready'] ? 'issue'
                    : ($authority->allows($permitApplication, $request->user(), 'releasing') && $completion?->issued_at !== null && $completion->released_at === null ? 'release' : null)),
            'applicationUrl' => route('staff.permit-applications.show', $permitApplication, false),
            'verificationUrl' => $completion?->released_at !== null ? $verification->handle($permitApplication)['view_url'] : null,
        ]);
    }

    public function store(StoreOrdinaryUatPermitRequest $request, PermitApplication $permitApplication, RecordOrdinaryUatMayoralAuthorization $authorize, IssueSyntheticLifecyclePermit $issue, ReleaseSyntheticLifecyclePermit $release): RedirectResponse
    {
        try {
            match ($request->validated('ceremony')) {
                'authorize' => $authorize->handle($permitApplication, $request->user()),
                'issue' => $issue->handle($permitApplication, $request->user()),
                'release' => $release->handle($permitApplication, $request->user()),
                default => throw new DomainException('Unknown UAT ceremony.'),
            };
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['ceremony' => $exception->getMessage()]);
        }

        return redirect()->route('staff.ordinary-uat-permit.show', $permitApplication);
    }
}
