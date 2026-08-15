<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\DescribeCitizenPaymentSchedule;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentScheduleController extends Controller
{
    public function __construct(
        private readonly DescribeCitizenPaymentSchedule $describeCitizenPaymentSchedule,
    ) {}

    public function show(Request $request, int $paymentSchedule): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplicationFinancials->value);

        $ownedPaymentSchedule = PaymentSchedule::query()
            ->whereKey($paymentSchedule)
            ->whereHas('permitApplication', fn ($query) => $query->whereBelongsTo($request->user(), 'submittedBy'))
            ->firstOrFail();

        return Inertia::render('citizen/payment-schedules/Show', [
            'paymentSchedule' => $this->describeCitizenPaymentSchedule->handle($ownedPaymentSchedule),
        ]);
    }
}
