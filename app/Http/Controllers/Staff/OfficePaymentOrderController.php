<?php

namespace App\Http\Controllers\Staff;

use App\Actions\ConfirmOfficePaymentOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ConfirmOfficePaymentOrderRequest;
use App\Models\BploRoutingWork;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;

class OfficePaymentOrderController extends Controller
{
    public function store(
        ConfirmOfficePaymentOrderRequest $request,
        PermitApplication $permitApplication,
        BploRoutingWork $work,
        ConfirmOfficePaymentOrder $confirm,
    ): RedirectResponse {
        abort_unless($work->determination()->where('permit_application_id', $permitApplication->id)->exists(), 404);
        $confirm->handle($work, $request->validated('items'), $request->user(), $request->file('signature_facsimile'));

        return back()->with('success', 'Payment Order confirmed.');
    }
}
