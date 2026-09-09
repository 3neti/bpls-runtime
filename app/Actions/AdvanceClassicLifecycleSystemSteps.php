<?php

namespace App\Actions;

use App\Models\LifecycleCleanroomRun;
use LogicException;

final class AdvanceClassicLifecycleSystemSteps
{
    public function __construct(
        private readonly ResolveLifecycleCleanroomState $resolveState,
        private readonly AdvanceLifecycleCleanroom $advance,
        private readonly RecordClassicLifecycleCeremonyEvent $recordEvent,
    ) {}

    public function handle(LifecycleCleanroomRun $run): LifecycleCleanroomRun
    {
        for ($guard = 0; $guard < 5; $guard++) {
            $state = $this->resolveState->handle($run->fresh());
            $next = data_get($state, 'progress.next_step');
            if (! is_array($next) || ($next['mode'] ?? null) !== 'system_action') {
                return $run->fresh();
            }
            $step = (string) ($next['key'] ?? '');
            $run = $this->advance->handle($run);
            $this->recordEvent->record($run, 'system_step_completed', context: [
                'completed_step' => $step,
            ]);
        }

        throw new LogicException('Classic Lifecycle system-step guard refused an unexpected execution loop.');
    }
}
