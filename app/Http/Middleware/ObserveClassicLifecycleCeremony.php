<?php

namespace App\Http\Middleware;

use App\Actions\AdvanceClassicLifecycleSystemSteps;
use App\Actions\RecordClassicLifecycleCeremonyEvent;
use App\Actions\ResolveLifecycleCleanroomState;
use App\Models\Assessment;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use App\Models\PermitApplication;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ObserveClassicLifecycleCeremony
{
    public function __construct(
        private readonly RecordClassicLifecycleCeremonyEvent $recordEvent,
        private readonly ResolveLifecycleCleanroomState $resolveState,
        private readonly AdvanceClassicLifecycleSystemSteps $advanceSystemSteps,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $next($request);
        }
        $run = $this->recordEvent->runForUser($user);
        $before = $run instanceof LifecycleCleanroomRun ? $this->resolveState->handle($run) : null;
        $response = $next($request);

        if (! $run instanceof LifecycleCleanroomRun || $response->getStatusCode() >= 400) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['login.store', 'logout', 'register.store'], true)) {
            return $response;
        }
        $application = $this->application($request, $run);
        if ($request->isMethod('GET')) {
            $event = match ($routeName) {
                'staff.work.index' => 'inbox_opened',
                'citizen.permit-applications.create' => 'application_form_opened',
                'citizen.permit-applications.show',
                'citizen.permit-applications.edit',
                'staff.permit-applications.evaluation.show',
                'staff.permit-applications.assessments.show',
                'staff.payment-schedules.show',
                'staff.permit-applications.show' => 'application_opened',
                default => null,
            };
            if (is_string($event)) {
                $this->recordEvent->record($run, $event, $user, $routeName, $application);
            }

            return $response;
        }

        $this->recordEvent->record($run, 'action_request_completed', $user, $routeName, $application);
        $run = $this->advanceSystemSteps->handle($run);
        $after = $this->resolveState->handle($run);
        if (data_get($after, 'progress.completed_steps') !== data_get($before, 'progress.completed_steps')) {
            $this->recordEvent->record($run, 'canonical_state_advanced', $user, $routeName, $application, [
                'from' => data_get($before, 'progress.completed_steps'),
                'to' => data_get($after, 'progress.completed_steps'),
            ]);
        }

        return $response;
    }

    private function application(Request $request, LifecycleCleanroomRun $run): ?PermitApplication
    {
        $parameter = $request->route('permitApplication');
        if ($parameter instanceof PermitApplication) {
            return $parameter;
        }
        $assessment = $request->route('assessment');
        if ($assessment instanceof Assessment) {
            return $assessment->permitApplication;
        }
        $schedule = $request->route('paymentSchedule');
        if ($schedule instanceof PaymentSchedule) {
            return $schedule->permitApplication;
        }

        return is_int($run->new_application_id)
            ? PermitApplication::query()->find($run->new_application_id)
            : null;
    }
}
