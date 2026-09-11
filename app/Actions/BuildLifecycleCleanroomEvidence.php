<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;

class BuildLifecycleCleanroomEvidence
{
    public function __construct(private readonly ResolveLifecycleCleanroomState $resolveState) {}

    /** @return array<string, mixed> */
    public function handle(LifecycleCleanroomRun $run): array
    {
        $state = $this->resolveState->handle($run);
        $complete = data_get($state, 'progress.complete') === true;
        $application = $this->application($run);
        $actorPayload = data_get($state, 'actors', []);
        $actors = is_array($actorPayload) ? $actorPayload : [];
        $nextActor = collect($actors)->firstWhere('is_next', true);
        $stepPayload = data_get($state, 'steps', []);
        $steps = is_array($stepPayload) ? $stepPayload : [];

        return [
            'public_id' => $run->public_id,
            'ceremony' => data_get($run->actor_manifest, 'ceremony', LifecycleCleanroomRun::CeremonyLegacyRegression),
            'ceremony_label' => $this->ceremonyLabel($run),
            'status' => $this->status($run, $complete),
            'progress' => [
                'completed_steps' => (int) data_get($state, 'progress.completed_steps', 0),
                'total_steps' => (int) data_get($state, 'progress.total_steps', 0),
                'percent' => (int) data_get($state, 'progress.percent', 0),
                'complete' => $complete,
                'blocked' => data_get($state, 'progress.blocked') === true,
                'next_task' => data_get($state, 'progress.next_step.milestone'),
                'next_actor' => is_array($nextActor) ? ($nextActor['label'] ?? null) : null,
            ],
            'application' => $application instanceof PermitApplication ? [
                'id' => $application->id,
                'tracking_reference' => $application->tracking_reference,
                'status' => $application->status->value,
            ] : null,
            'timestamps' => [
                'created_at' => $run->created_at?->toIso8601String(),
                'completed_at' => $complete ? $this->completedAt($run, $application) : null,
                'retained_at' => $run->closed_at?->toIso8601String(),
            ],
            'disposition' => $run->status === 'closed'
                ? ($complete ? 'Completed evidence retained' : 'Incomplete evidence retained')
                : ($complete ? 'Completed; ready to retain' : 'Ceremony in progress'),
            'event_count' => $run->ceremonyEvents()->count(),
            'steps' => collect($steps)->map(fn (array $step): array => [
                'key' => $step['key'],
                'label' => $step['label'],
                'status' => $step['status'],
            ])->all(),
        ];
    }

    private function application(LifecycleCleanroomRun $run): ?PermitApplication
    {
        $applicationId = $run->renewal_application_id ?? $run->new_application_id;

        return is_int($applicationId) ? PermitApplication::query()->find($applicationId) : null;
    }

    private function status(LifecycleCleanroomRun $run, bool $complete): string
    {
        if ($run->status === 'closed') {
            return 'Retained';
        }

        return $complete ? 'Completed' : 'Active';
    }

    private function ceremonyLabel(LifecycleCleanroomRun $run): string
    {
        return match (data_get($run->actor_manifest, 'ceremony')) {
            LifecycleCleanroomRun::CeremonyClassicLifecycleV1 => 'Classic lifecycle',
            LifecycleCleanroomRun::CeremonyNelsonReconciliationV1 => 'Nelson reconciliation V1',
            default => 'Certified lifecycle regression',
        };
    }

    private function completedAt(LifecycleCleanroomRun $run, ?PermitApplication $application): ?string
    {
        $event = $run->ceremonyEvents()
            ->whereNotNull('completed_stage_count')
            ->latest('occurred_at')
            ->first();
        if ($event !== null) {
            return $event->occurred_at->toIso8601String();
        }
        if ($application !== null) {
            $completion = $application->provisionalUatPermitCompletion;
            if ($completion !== null && $completion->released_at !== null) {
                return $completion->released_at->toIso8601String();
            }
        }

        return $run->updated_at?->toIso8601String();
    }
}
