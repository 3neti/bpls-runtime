<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LodgeLifecycleCleanroomApplication
{
    public function __construct(
        private readonly CaptureLifecycleCleanroomIntake $captureIntake,
        private readonly SubmitCitizenPermitApplication $submitApplication,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{run: LifecycleCleanroomRun, application: PermitApplication}
     */
    public function handle(
        Request $request,
        array $data,
        string $cleanroomRunId,
        bool $undertakingAccepted,
    ): array {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new DomainException('The cleanroom applicant session is unavailable.');
        }

        try {
            $result = DB::transaction(function () use ($request, $data, $cleanroomRunId, $undertakingAccepted, $actor): array {
                $run = LifecycleCleanroomRun::query()
                    ->where('public_id', $cleanroomRunId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                $this->assertLodgeable($run, $actor);

                if ($run->new_application_id !== null) {
                    $application = PermitApplication::query()
                        ->with('business')
                        ->findOrFail($run->new_application_id);
                    $this->assertOwnedApplication($run, $application, $actor);
                } else {
                    $request->session()->put('lifecycle_cleanroom_intake_run_id', $run->id);
                    $application = $this->captureIntake->create($request, $data);
                }

                $application = $this->submitApplication->handle($application, $actor, $undertakingAccepted);
                $this->captureDeclarationOwnership($run, $application);

                return ['run' => $run->fresh(), 'application' => $application];
            }, 3);
        } catch (\Throwable $exception) {
            $run = LifecycleCleanroomRun::query()->where('public_id', $cleanroomRunId)->first();
            if ($run instanceof LifecycleCleanroomRun && $run->new_application_id === null) {
                $request->session()->put('lifecycle_cleanroom_intake_run_id', $run->id);
            }

            throw $exception;
        }

        $request->session()->forget('lifecycle_cleanroom_intake_run_id');

        return $result;
    }

    private function assertLodgeable(?LifecycleCleanroomRun $run, User $actor): void
    {
        if (! $run instanceof LifecycleCleanroomRun
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false
            || data_get($run->actor_manifest, 'actors.citizen.user_id') !== $actor->id) {
            throw new DomainException('This Lifecycle Cleanroom cannot accept the Application.');
        }
    }

    private function assertOwnedApplication(LifecycleCleanroomRun $run, PermitApplication $application, User $actor): void
    {
        if ($application->submitted_by_id !== $actor->id
            || $application->business->business_owner_id !== $actor->business_owner_id
            || data_get($application->metadata, 'lifecycle_cleanroom.run_id') !== $run->public_id) {
            throw new DomainException('The existing cleanroom Application does not belong to the applicant.');
        }
    }

    private function captureDeclarationOwnership(LifecycleCleanroomRun $run, PermitApplication $application): void
    {
        $declarationId = $application->declaration()->value('id');
        if (! is_int($declarationId)) {
            throw new DomainException('The lodged Application has no frozen applicant declaration.');
        }

        $run->refresh();
        $manifest = $run->owned_resource_manifest;
        $manifest['permit_application_declaration_ids'] = [$declarationId];
        $run->update(['owned_resource_manifest' => $manifest]);
    }
}
