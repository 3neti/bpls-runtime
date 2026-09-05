<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\ProvisionalUatPermitCompletion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReleaseSyntheticLifecyclePermit
{
    public function handle(PermitApplication $permitApplication, User $actor): ProvisionalUatPermitCompletion
    {
        return DB::transaction(function () use ($permitApplication, $actor): ProvisionalUatPermitCompletion {
            $application = PermitApplication::query()->whereKey($permitApplication)->lockForUpdate()->firstOrFail();
            $runId = data_get($application->metadata, 'lifecycle_cleanroom.run_id');
            $run = is_string($runId) ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first() : null;
            if (! $run instanceof LifecycleCleanroomRun
                || data_get($run->actor_manifest, 'actors.releasing_officer.user_id') !== $actor->id) {
                throw new DomainException('Only the commissioned cleanroom Releasing Officer may release this specimen.');
            }
            $completion = $application->provisionalUatPermitCompletion()->lockForUpdate()->first();
            if (! $completion instanceof ProvisionalUatPermitCompletion || $completion->issued_at === null) {
                throw new DomainException('Permit issuance and release are distinct; issue the specimen before BPLO release.');
            }
            if ($completion->released_at !== null) {
                return $completion;
            }
            $completion->fill([
                'released_by_id' => $actor->id,
                'status' => 'released_synthetic',
                'released_at' => now(),
            ])->save();

            return $completion->refresh();
        }, 3);
    }
}
