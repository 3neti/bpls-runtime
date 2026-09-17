<?php

namespace App\Actions;

use App\Models\InstitutionalPositionAssignment;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use LogicException;

class AuthorizeRoutedOfficeActor
{
    public function handle(
        PermitApplication $application,
        string $officeCode,
        User $actor,
        ?int $explicitlyAuthorizedActorId = null,
    ): void {
        if (! $this->allows($application, $officeCode, $actor, $explicitlyAuthorizedActorId)) {
            throw new LogicException("Only the authorized routed [{$officeCode}] office actor may perform this action.");
        }
    }

    public function allows(
        PermitApplication $application,
        string $officeCode,
        User $actor,
        ?int $explicitlyAuthorizedActorId = null,
    ): bool {
        $runId = data_get($application->metadata, 'lifecycle_cleanroom.run_id');
        if (is_string($runId)) {
            $run = LifecycleCleanroomRun::query()->where('public_id', $runId)->first();

            return $run instanceof LifecycleCleanroomRun
                && $run->status === 'active'
                && data_get($run->actor_manifest, 'semantic_classification') === 'synthetic_only'
                && data_get($run->actor_manifest, 'production_liability') === false
                && data_get($run->actor_manifest, 'actors.'.$officeCode.'.user_id') === $actor->id;
        }

        if ($explicitlyAuthorizedActorId !== null) {
            return $explicitlyAuthorizedActorId === $actor->id;
        }

        if ($actor->hasRole($officeCode)) {
            return true;
        }

        // Preview personas retain their preview roles; municipal responsibility
        // is established by the active institutional position assignment.
        return InstitutionalPositionAssignment::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('position.capabilityRole', fn ($query) => $query->where('code', $officeCode))
            ->exists();
    }
}
