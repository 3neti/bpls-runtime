<?php

namespace App\Actions;

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

        return $actor->hasRole($officeCode);
    }
}
