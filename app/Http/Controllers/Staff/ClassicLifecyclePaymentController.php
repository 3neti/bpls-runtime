<?php

namespace App\Http\Controllers\Staff;

use App\Actions\SimulateLifecycleQrPhPayment;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\LifecycleCleanroomRun;
use App\Models\PaymentSchedule;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ClassicLifecyclePaymentController extends Controller
{
    public function store(
        Request $request,
        PaymentSchedule $paymentSchedule,
        SimulateLifecycleQrPhPayment $simulatePayment,
    ): RedirectResponse {
        Gate::authorize(UserPermission::RecordCollections->value);

        $runId = data_get($paymentSchedule->permitApplication->metadata, 'lifecycle_cleanroom.run_id');
        $run = is_string($runId)
            ? LifecycleCleanroomRun::query()->where('public_id', $runId)->first()
            : null;

        if (! $run instanceof LifecycleCleanroomRun || ! $run->isClassicLifecycleV1() || $run->status !== 'active') {
            throw new DomainException('Payment simulation is available only for an active Classic Lifecycle ceremony.');
        }

        if (data_get($run->actor_manifest, 'actors.cashier.user_id') !== $request->user()->id) {
            abort(403, 'Only the commissioned Classic Lifecycle cashier may simulate this payment.');
        }

        try {
            $simulatePayment->handle($run);
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return to_route('staff.payment-schedules.show', $paymentSchedule)
            ->with('success', 'Synthetic QR Ph payment recorded. Issue every required Official Receipt to continue.');
    }
}
