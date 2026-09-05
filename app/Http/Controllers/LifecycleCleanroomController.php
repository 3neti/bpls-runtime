<?php

namespace App\Http\Controllers;

use App\Actions\AdvanceLifecycleCleanroom;
use App\Actions\AuthenticateLifecycleCleanroomActor;
use App\Actions\AuthenticateStakeholderPreviewPersona;
use App\Actions\BuildBploRoutingTask;
use App\Actions\BuildExecutablePermitApplicationDocument;
use App\Actions\BuildLifecycleOfficeReviewHandoff;
use App\Actions\ConfirmLifecycleRoutineOfficeDefaults;
use App\Actions\ResolveLifecycleCleanroomState;
use App\Actions\SimulateLifecycleOfficeReviews;
use App\Actions\SimulateLifecycleQrPhPayment;
use App\Actions\StartLifecycleCleanroom;
use App\Data\Application\ApplicationDataResolver;
use App\Enums\StakeholderPreviewPersona;
use App\Enums\UserPermission;
use App\Http\Requests\RunLifecycleCleanroomMilestoneRequest;
use App\Models\LifecycleCleanroomRun;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class LifecycleCleanroomController extends Controller
{
    public function officeReviewsAssigned(
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        int $applicationYear,
        BuildLifecycleOfficeReviewHandoff $buildHandoff,
    ): Response {
        abort_unless(in_array($applicationYear, [2025, 2026], true), 404);

        try {
            $handoff = $buildHandoff->handle($lifecycleCleanroomRun, $applicationYear);
        } catch (LogicException) {
            abort(404);
        }

        return Inertia::render('stakeholder-preview/OfficeReviewsAssigned', [
            'handoff' => $handoff,
        ]);
    }

    public function start(Request $request, StartLifecycleCleanroom $start): RedirectResponse
    {
        $start->handle($request->user());

        return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'A cleanroom is ready. Run Next Step opens the first real product form.');
    }

    public function confirmRoutineOfficeDefaults(
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        int $applicationYear,
        ConfirmLifecycleRoutineOfficeDefaults $confirmDefaults,
    ): RedirectResponse {
        try {
            $result = $confirmDefaults->handle($lifecycleCleanroomRun, $applicationYear);
        } catch (LogicException $exception) {
            return back()->withErrors(['cleanroom' => $exception->getMessage()]);
        }

        $message = $result['confirmed'].' routine default '.str('determination')->plural($result['confirmed']).' confirmed under the concerned-office identities.';
        if ($result['manual'] > 0) {
            $message .= ' '.$result['manual'].' '.str('determination')->plural($result['manual']).' still require office review.';
        }

        return back()->with('success', $message);
    }

    public function simulateOfficeReviews(
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        int $applicationYear,
        SimulateLifecycleOfficeReviews $simulateReviews,
    ): RedirectResponse {
        try {
            $count = $simulateReviews->handle($lifecycleCleanroomRun, $applicationYear);
        } catch (LogicException $exception) {
            return back()->withErrors(['cleanroom' => $exception->getMessage()]);
        }

        return back()->with('success', $count.' synthetic office '.str('review')->plural($count).' completed. Each audit record states that no real inspection occurred.');
    }

    public function simulateQrPhPayment(
        Request $request,
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        SimulateLifecycleQrPhPayment $simulatePayment,
        AuthenticateLifecycleCleanroomActor $authenticateCleanroomActor,
        AuthenticateStakeholderPreviewPersona $authenticatePreviewPersona,
    ): RedirectResponse {
        try {
            $collection = $simulatePayment->handle($lifecycleCleanroomRun);
        } catch (LogicException $exception) {
            return back()->withErrors(['cleanroom' => $exception->getMessage()]);
        }

        $message = "Synthetic QR Ph payment recorded as Collection #{$collection->id}. No real funds moved. Issue the seven-digit manual Official Receipt number, then print or open its PDF.";
        if (is_array($lifecycleCleanroomRun->actor('cashier'))) {
            return redirect()->to($authenticateCleanroomActor->handle($request, $lifecycleCleanroomRun, 'cashier'))->with('success', $message);
        }

        $authenticatePreviewPersona->handle($request, StakeholderPreviewPersona::Cashier, requireCurrentPreviewAccount: true);

        return to_route('staff.payment-schedules.show', $collection->payment_schedule_id)->with('success', $message);
    }

    public function runNext(
        Request $request,
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        ResolveLifecycleCleanroomState $resolveState,
        AdvanceLifecycleCleanroom $advance,
        AuthenticateLifecycleCleanroomActor $authenticate,
    ): RedirectResponse {
        $state = $resolveState->handle($lifecycleCleanroomRun);
        if (is_string($blocker = data_get($state, 'progress.blocker'))) {
            return to_route('stakeholder-preview.lifecycle-laboratory.index')->withErrors(['cleanroom' => $blocker]);
        }
        $next = data_get($state, 'progress.next_step');
        if (! is_array($next)) {
            return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'The cleanroom chronology is complete.');
        }
        if (! $this->expectedStepMatches($request, $next)) {
            return to_route('stakeholder-preview.lifecycle-laboratory.index')
                ->withErrors(['cleanroom' => 'The next Application task changed. Review the newly highlighted actor before continuing.']);
        }
        if ($next['mode'] === 'system_action') {
            $advance->handle($lifecycleCleanroomRun);

            if (in_array($next['key'], ['evaluation_initialized', 'renewal_evaluation_initialized'], true)) {
                return to_route('stakeholder-preview.lifecycle-laboratory.cleanrooms.office-reviews-assigned', [
                    $lifecycleCleanroomRun,
                    $next['year'],
                ])->with('success', 'Office evaluation work created. No amount became payable and no Assessment was created.');
            }

            return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', $next['label'].' completed through the canonical action boundary.');
        }

        return $this->openHumanStep($request, $lifecycleCleanroomRun, $next, $authenticate);
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
            if (is_string($blocker = data_get($state, 'progress.blocker'))) {
                return to_route('stakeholder-preview.lifecycle-laboratory.index')->withErrors(['cleanroom' => $blocker]);
            }
            $next = data_get($state, 'progress.next_step');
            if (! is_array($next)) {
                return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'The two-year cleanroom chronology is complete.');
            }
            $nextSequence = $this->stepIndex($steps, is_string($next['key'] ?? null) ? $next['key'] : '');
            if (is_int($nextSequence) && $nextSequence > $targetSequence) {
                return to_route('stakeholder-preview.lifecycle-laboratory.index')->with('success', 'Selected milestone complete.');
            }
            if (($next['mode'] ?? null) !== 'system_action') {
                return $this->openHumanStep($request, $lifecycleCleanroomRun, $next, $authenticate);
            }
            $advance->handle($lifecycleCleanroomRun);
        }

        throw new LogicException('Cleanroom milestone guard refused an unexpected execution loop.');
    }

    public function enterActor(Request $request, LifecycleCleanroomRun $lifecycleCleanroomRun, string $actor, AuthenticateLifecycleCleanroomActor $authenticate): RedirectResponse
    {
        return redirect()->to($authenticate->handle($request, $lifecycleCleanroomRun, $actor, 'stakeholder-preview.lifecycle-cleanroom-application.show'));
    }

    public function showApplication(
        Request $request,
        LifecycleCleanroomRun $lifecycleCleanroomRun,
        ApplicationDataResolver $resolver,
        BuildExecutablePermitApplicationDocument $buildDocument,
        BuildBploRoutingTask $buildRoutingTask,
    ): Response {
        $actorIds = collect($lifecycleCleanroomRun->actors())->pluck('user_id')->filter()->all();
        abort_unless(
            $lifecycleCleanroomRun->status === 'active'
            && data_get($lifecycleCleanroomRun->actor_manifest, 'semantic_classification') === 'synthetic_only'
            && data_get($lifecycleCleanroomRun->actor_manifest, 'production_liability') === false
            && in_array($request->user()?->id, $actorIds, true),
            404,
        );
        $applicationId = $lifecycleCleanroomRun->renewal_application_id ?? $lifecycleCleanroomRun->new_application_id;
        abort_unless(is_int($applicationId), 404);
        $application = PermitApplication::query()->findOrFail($applicationId);
        $viewer = $request->user();
        $requestedTask = $request->string('task')->toString();
        $routingTask = $requestedTask === 'bplo-routing'
            && $viewer->can(UserPermission::DetermineBploRouting->value)
                ? $buildRoutingTask->handle($application, $viewer)->toArray()
                : null;
        $initialTab = $request->string('tab')->toString();
        if (! in_array($initialTab, ['application', 'processing', 'fee_menu', 'payment_orders', 'assessment', 'payment', 'permit'], true)) {
            $initialTab = $routingTask === null ? 'application' : 'processing';
        }

        return Inertia::render('stakeholder-preview/LifecycleApplication', [
            'application' => $resolver->resolve($application, $request->user())->toArray(),
            'document' => $buildDocument->handle($application, $request->user()),
            'focus' => $routingTask === null ? '' : 'bplo-routing',
            'initialTab' => $initialTab,
            'routingTask' => $routingTask,
            'scenario' => ['id' => 'cleanroom', 'run_id' => $lifecycleCleanroomRun->public_id],
        ]);
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

    /** @param array<string, mixed> $next */
    private function expectedStepMatches(Request $request, array $next): bool
    {
        $expectedStep = $request->input('expected_step_key');
        $expectedActor = $request->input('expected_actor_key');

        return (! is_string($expectedStep) || $expectedStep === ($next['key'] ?? null))
            && (! is_string($expectedActor) || $expectedActor === ($next['actor'] ?? null));
    }

    /** @param array<string, mixed> $next */
    private function openHumanStep(
        Request $request,
        LifecycleCleanroomRun $run,
        array $next,
        AuthenticateLifecycleCleanroomActor $authenticate,
    ): RedirectResponse {
        $step = is_string($next['key'] ?? null) ? $next['key'] : '';
        $actor = is_string($next['actor'] ?? null) ? $next['actor'] : '';
        abort_if($actor === '', 404);

        if (str_contains($step, 'bplo_routing')) {
            $url = $authenticate->handle($request, $run, $actor, 'stakeholder-preview.lifecycle-cleanroom-application.show');

            return redirect()->to($url.'?'.http_build_query([
                'tab' => 'processing',
                'task' => 'bplo-routing',
            ]));
        }

        return redirect()->to($authenticate->handle($request, $run, $actor, $this->destination($step)));
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
