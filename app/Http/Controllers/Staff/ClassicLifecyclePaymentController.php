<?php

namespace App\Http\Controllers\Staff;

use App\Actions\SimulateAuthorizedQrPhPayment;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ClassicLifecyclePaymentController extends Controller
{
    public function store(
        Request $request,
        PaymentSchedule $paymentSchedule,
        SimulateAuthorizedQrPhPayment $simulatePayment,
    ): RedirectResponse {
        Gate::authorize(UserPermission::RecordCollections->value);

        $validated = $request->validate(['attempt_id' => ['required', 'integer', 'min:1']]);

        try {
            $simulatePayment->handle($paymentSchedule, $request->user(), (int) $validated['attempt_id']);
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return to_route('staff.payment-schedules.show', $paymentSchedule)
            ->with('success', 'Synthetic QR Ph payment recorded. Issue every required Official Receipt to continue.');
    }
}
