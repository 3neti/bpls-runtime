<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RecordPaymentScheduleCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePaymentScheduleCollectionRequest;
use App\Models\PaymentSchedule;
use Illuminate\Http\RedirectResponse;

class PaymentScheduleCollectionController extends Controller
{
    public function store(
        StorePaymentScheduleCollectionRequest $request,
        PaymentSchedule $paymentSchedule,
        RecordPaymentScheduleCollection $recordPaymentScheduleCollection,
    ): RedirectResponse {
        abort_if($paymentSchedule->xChangePayment()->exists(), 409, 'This schedule has a QR Ph payable. An explicit authorized rail transition is required before over-the-counter collection.');

        $recordPaymentScheduleCollection->handle(
            $paymentSchedule,
            $request->validatedForCollection(),
            $request->user(),
        );

        return to_route('staff.payment-schedules.show', $paymentSchedule);
    }
}
