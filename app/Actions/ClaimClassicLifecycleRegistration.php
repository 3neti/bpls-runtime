<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRegistrationInvitation;
use App\Models\LifecycleCleanroomRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimClassicLifecycleRegistration
{
    public function __construct(private readonly RecordClassicLifecycleCeremonyEvent $recordEvent) {}

    public function handle(User $user, string $token): LifecycleCleanroomRun
    {
        return DB::transaction(function () use ($user, $token): LifecycleCleanroomRun {
            $invitation = LifecycleCleanroomRegistrationInvitation::query()
                ->with('run')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();
            if (! $invitation instanceof LifecycleCleanroomRegistrationInvitation
                || $invitation->claimed_at !== null
                || $invitation->expires_at->isPast()
                || ! $invitation->run->isClassicLifecycleV1()
                || $invitation->run->status !== 'active') {
                throw ValidationException::withMessages([
                    'classic_cleanroom_invitation' => 'This Classic Lifecycle registration invitation is invalid or expired.',
                ]);
            }

            $run = LifecycleCleanroomRun::query()->whereKey($invitation->run)->lockForUpdate()->firstOrFail();
            $roleId = (int) $user->roles()->where('code', 'citizen')->sole()->getKey();
            $manifest = $run->actor_manifest;
            $actors = is_array($manifest['actors'] ?? null) ? $manifest['actors'] : [];
            $actors['citizen'] = [
                'label' => $user->name,
                'user_id' => $user->id,
                'role_id' => $roleId,
            ];
            $manifest['actors'] = $actors;
            $actorUserIds = [];
            $actorRoleIds = [];
            foreach ($actors as $actor) {
                if (! is_array($actor)) {
                    continue;
                }
                if (is_int($actor['user_id'] ?? null)) {
                    $actorUserIds[] = $actor['user_id'];
                }
                if (is_int($actor['role_id'] ?? null)) {
                    $actorRoleIds[] = $actor['role_id'];
                }
            }
            sort($actorUserIds);
            $actorRoleIds = array_values(array_unique($actorRoleIds));
            sort($actorRoleIds);
            $manifest['actor_user_ids'] = $actorUserIds;
            $manifest['actor_role_ids'] = $actorRoleIds;
            $run->update(['actor_manifest' => $manifest]);

            $owned = $run->owned_resource_manifest;
            $userIds = is_array($owned['user_ids'] ?? null) ? $owned['user_ids'] : [];
            $userIds[] = $user->id;
            $userIds = array_values(array_unique(array_filter($userIds, is_int(...))));
            sort($userIds);
            $owned['user_ids'] = $userIds;
            $run->update(['owned_resource_manifest' => $owned]);
            $invitation->update(['claimed_by_id' => $user->id, 'claimed_at' => now()]);
            request()->session()->put('lifecycle_cleanroom_intake_run_id', $run->id);
            $this->recordEvent->record($run, 'citizen_registered', $user, 'register.store');

            return $run->fresh();
        }, 3);
    }
}
