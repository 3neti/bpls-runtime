<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRegistrationInvitation;
use App\Models\LifecycleCleanroomRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StartClassicLifecycleCleanroom
{
    public function __construct(
        private readonly StartLifecycleCleanroom $startCleanroom,
        private readonly RecordClassicLifecycleCeremonyEvent $recordEvent,
    ) {}

    /** @return array{run: LifecycleCleanroomRun, registration_url: string} */
    public function handle(User $startedBy): array
    {
        return DB::transaction(function () use ($startedBy): array {
            $run = $this->startCleanroom->handle(
                $startedBy,
                LifecycleCleanroomRun::CeremonyClassicLifecycleV1,
                LifecycleCleanroomRun::SourceSpecimenCal2026001New2025,
            );
            $token = Str::random(64);
            $expiresAt = now()->addHour();
            LifecycleCleanroomRegistrationInvitation::query()->updateOrCreate(
                ['lifecycle_cleanroom_run_id' => $run->id],
                [
                    'token_hash' => hash('sha256', $token),
                    'claimed_by_id' => null,
                    'expires_at' => $expiresAt,
                    'claimed_at' => null,
                ],
            );
            if (! $run->ceremonyEvents()->where('event', 'classic_cleanroom_started')->exists()) {
                $this->recordEvent->record($run, 'classic_cleanroom_started', context: [
                    'registration_invitation_expires_at' => $expiresAt->toIso8601String(),
                ]);
            }

            return [
                'run' => $run,
                'registration_url' => route('register', ['classic_cleanroom_invitation' => $token], false),
            ];
        }, 3);
    }
}
