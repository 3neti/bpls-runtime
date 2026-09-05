<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use Illuminate\Http\Request;

class ResolveLifecycleCleanroomIntake
{
    public function handle(Request $request): ?LifecycleCleanroomRun
    {
        $runId = $request->session()->get('lifecycle_cleanroom_intake_run_id');
        $publicId = $request->input('lifecycle_cleanroom_run_id');
        if ((! is_int($runId) && ! is_string($publicId)) || ! $request->user()) {
            return null;
        }

        $run = LifecycleCleanroomRun::query()
            ->when(
                is_int($runId),
                fn ($query) => $query->whereKey($runId),
                fn ($query) => $query->where('public_id', $publicId),
            )
            ->where('status', 'active')
            ->first();
        if (! $run instanceof LifecycleCleanroomRun
            || data_get($run->actor_manifest, 'semantic_classification') !== 'synthetic_only'
            || data_get($run->actor_manifest, 'production_liability') !== false
            || data_get($run->actor_manifest, 'actors.citizen.user_id') !== $request->user()->id) {
            return null;
        }

        return $run;
    }
}
