<?php

namespace App\Actions;

use App\LifecycleScenarios\LifecycleCleanroomDefinition;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;

class CaptureLifecycleCleanroomIntake
{
    public function __construct(
        private readonly ResolveLifecycleCleanroomIntake $resolveIntake,
        private readonly CreateCitizenPermitApplicationDraft $createDraft,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Request $request, array $data): PermitApplication
    {
        return DB::transaction(function () use ($request, $data): PermitApplication {
            $application = $this->createDraft->handle($data, $request->user());
            $this->capture($request, $application);

            return $application;
        }, 3);
    }

    private function capture(Request $request, PermitApplication $application): void
    {
        $run = $this->resolveIntake->handle($request);
        if (! $run instanceof LifecycleCleanroomRun) {
            return;
        }

        $lockedRun = LifecycleCleanroomRun::query()->whereKey($run)->lockForUpdate()->firstOrFail();
        if ($lockedRun->new_application_id !== null
            || $application->submitted_by_id !== $request->user()?->id
            || $application->business->business_owner_id !== $request->user()?->business_owner_id) {
            throw new LogicException('Cleanroom intake ownership changed; no application was claimed.');
        }

        $metadata = $application->metadata ?? [];
        $metadata['lifecycle_cleanroom'] = [
            'run_id' => $lockedRun->public_id,
            'definition_revision' => LifecycleCleanroomDefinition::Revision,
            'scenario_id' => 'new-application-happy-path',
            'semantic_classification' => 'synthetic_only',
            'production_liability' => false,
        ];
        $metadata['business_permit_evaluation'] = [
            'semantic_classification' => 'provisional_uat',
            'scenario_id' => 'new-application-happy-path',
            'cleanroom_run_id' => $lockedRun->public_id,
            'production_liability' => false,
        ];
        $application->forceFill(['metadata' => $metadata])->save();

        $manifest = $lockedRun->owned_resource_manifest;
        $manifest['business_owner_ids'] = [$application->business->business_owner_id];
        $manifest['business_ids'] = [$application->business_id];
        $manifest['permit_application_ids'] = [$application->id];
        $manifest['permit_application_line_ids'] = $application->lines()->pluck('id')->sort()->values()->all();
        $manifest['reference_line_of_business_ids'] = $application->lines()->pluck('line_of_business_id')->filter()->sort()->values()->all();
        $lockedRun->update([
            'new_application_id' => $application->id,
            'owned_resource_manifest' => $manifest,
        ]);
        $request->session()->forget('lifecycle_cleanroom_intake_run_id');
    }
}
