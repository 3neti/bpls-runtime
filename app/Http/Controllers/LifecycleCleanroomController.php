<?php

namespace App\Http\Controllers;

use App\Actions\AdvanceLifecycleCleanroom;
use App\Actions\AuthenticateLifecycleCleanroomActor;
use App\Actions\ResolveLifecycleCleanroomState;
use App\Actions\StartLifecycleCleanroom;
use App\Http\Requests\RunLifecycleCleanroomMilestoneRequest;
use App\Models\LifecycleCleanroomRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class LifecycleCleanroomController extends Controller
{
    public function start(Request $request, StartLifecycleCleanroom $start): RedirectResponse
    {
        $start->handle($request->user());

        return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'A cleanroom is ready. Run Next Step opens the first real product form.');
    }

    public function runNext(
        Request $request,
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        ResolveLifecycleCleanroomState $resolveState,
        AdvanceLifecycleCleanroom $advance,
        AuthenticateLifecycleCleanroomActor $authenticate,
    ): RedirectResponse {
        $state = $resolveState->handle($lifecycleCleanroomRun);
        $next = data_get($state, 'progress.next_step');
        if (! is_array($next)) {
            return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'The cleanroom chronology is complete.');
        }
        if ($next['mode'] === 'system_action') {
            $advance->handle($lifecycleCleanroomRun);

            return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', $next['label'].' completed through the canonical action boundary.');
        }

        return redirect()->to($authenticate->handle($request, $lifecycleCleanroomRun, $next['actor'], $this->destination($next['key'])));
    }

    public function runToMilestone(
        RunLifecycleCleanroomMilestoneRequest $request,
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        ResolveLifecycleCleanroomState $resolveState,
        AdvanceLifecycleCleanroom $advance,
        AuthenticateLifecycleCleanroomActor $authenticate,
    ): RedirectResponse {
        $target = $request->string('step_key')->toString();
        $lifecycleCleanroomRun->update(['target_step' => $target]);
        $initialState = $resolveState->handle($lifecycleCleanroomRun->fresh());
        $steps = is_array($initialState['steps'] ?? null) ? $initialState['steps'] : [];
        $targetSequence = $this->stepIndex($steps, $target);
        if (! is_int($targetSequence)) {
            return to_route('stakeholder-preview.lifecycle-laboratory.index');
        }

        for ($guard = 0; $guard < 22; $guard++) {
            $state = $resolveState->handle($lifecycleCleanroomRun->fresh());
            $next = data_get($state, 'progress.next_step');
            if (! is_array($next)) {
                return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'The two-year cleanroom chronology is complete.');
            }
            $nextSequence = $this->stepIndex($steps, is_string($next['key'] ?? null) ? $next['key'] : '');
            if (is_int($nextSequence) && $nextSequence > $targetSequence) {
                return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'Selected milestone complete.');
            }
            if (($next['mode'] ?? null) !== 'system_action') {
                return redirect()->to($authenticate->handle($request, $lifecycleCleanroomRun, $next['actor'], $this->destination($next['key'])));
            }
            $advance->handle($lifecycleCleanroomRun);
        }

        throw new LogicException('Cleanroom milestone guard refused an unexpected execution loop.');
    }

    public function enterActor(Request $request, LifecycleCleanroomRun $lifecycleCleanroomRun, string $actor, AuthenticateLifecycleCleanroomActor $authenticate): RedirectResponse
    {
        return redirect()->to($authenticate->handle($request, $lifecycleCleanroomRun, $actor));
    }

    public function close(LifecycleCleanroomRun $lifecycleCleanroomRun): RedirectResponse
    {
        if ($lifecycleCleanroomRun->status !== 'active') {
            throw new LogicException('Only an active cleanroom may be closed.');
        }
        $lifecycleCleanroomRun->update(['status' => 'closed', 'closed_at' => now(), 'target_step' => null]);

        return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'Cleanroom closed. Its synthetic evidence was retained; nothing was deleted.');
    }

    private function destination(string $step): ?string
    {
        if (str_contains($step, 'bplo_routing')) {
            return 'staff.permit-applications.evaluation.show';
        }
        if (str_contains($step, 'assessment_prepared')) {
            return 'staff.permit-applications.evaluation.show';
        }
        if (str_contains($step, 'treasury_counter_check')) {
            return 'staff.permit-applications.evaluation.show';
        }
        if (str_contains($step, 'treasurer_approved')) {
            return 'staff.permit-applications.assessments.show';
        }
        if (str_contains($step, 'payable_created')) {
            return 'staff.permit-applications.assessments.show';
        }

        return null;
    }

    /** @param array<mixed> $steps */
    private function stepIndex(array $steps, string $key): ?int
    {
        foreach ($steps as $index => $step) {
            if (is_int($index) && is_array($step) && ($step['key'] ?? null) === $key) {
                return $index;
            }
        }

        return null;
    }
}
