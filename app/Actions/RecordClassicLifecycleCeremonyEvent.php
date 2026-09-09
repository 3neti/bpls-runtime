<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomCeremonyEvent;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordClassicLifecycleCeremonyEvent
{
    /** @param array<string, mixed> $context */
    public function forUser(
        User $user,
        string $event,
        ?string $routeName = null,
        ?PermitApplication $application = null,
        array $context = [],
    ): ?LifecycleCleanroomCeremonyEvent {
        $run = $this->runForUser($user);
        if (! $run instanceof LifecycleCleanroomRun) {
            return null;
        }

        return $this->record($run, $event, $user, $routeName, $application, $context);
    }

    public function runForUser(User $user): ?LifecycleCleanroomRun
    {
        return LifecycleCleanroomRun::query()
            ->where('status', 'active')
            ->latest('id')
            ->get()
            ->first(fn (LifecycleCleanroomRun $candidate): bool => $candidate->isClassicLifecycleV1()
                && collect($candidate->actors())->contains(fn (array $actor): bool => $actor['user_id'] === $user->id));
    }

    /** @param array<string, mixed> $context */
    public function record(
        LifecycleCleanroomRun $run,
        string $event,
        ?User $user = null,
        ?string $routeName = null,
        ?PermitApplication $application = null,
        array $context = [],
    ): LifecycleCleanroomCeremonyEvent {
        return DB::transaction(function () use ($run, $event, $user, $routeName, $application, $context): LifecycleCleanroomCeremonyEvent {
            $lockedRun = LifecycleCleanroomRun::query()->whereKey($run)->lockForUpdate()->firstOrFail();
            $actorKey = $user instanceof User
                ? collect($lockedRun->actors())->search(fn (array $actor): bool => $actor['user_id'] === $user->id)
                : false;
            $state = app(ResolveLifecycleCleanroomState::class)->handle($lockedRun);

            return LifecycleCleanroomCeremonyEvent::query()->create([
                'lifecycle_cleanroom_run_id' => $lockedRun->id,
                'actor_user_id' => $user?->id,
                'permit_application_id' => $application instanceof PermitApplication ? $application->id : $lockedRun->new_application_id,
                'sequence' => ((int) $lockedRun->ceremonyEvents()->max('sequence')) + 1,
                'actor_key' => is_string($actorKey) ? $actorKey : null,
                'event' => $event,
                'route_name' => $routeName,
                'canonical_step' => data_get($state, 'progress.next_step.key'),
                'completed_stage_count' => data_get($state, 'progress.completed_steps'),
                'context' => $context === [] ? null : $context,
                'occurred_at' => now(),
            ]);
        }, 3);
    }
}
