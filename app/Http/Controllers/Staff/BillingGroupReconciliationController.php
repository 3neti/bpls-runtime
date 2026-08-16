<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateBillingGroupReconciliationEvidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreBillingGroupReconciliationRequest;
use App\Models\BillingGroup;
use Illuminate\Http\RedirectResponse;

class BillingGroupReconciliationController extends Controller
{
    public function store(
        StoreBillingGroupReconciliationRequest $request,
        BillingGroup $billingGroup,
        CreateBillingGroupReconciliationEvidence $createEvidence,
    ): RedirectResponse {
        $createEvidence->handle($billingGroup, $request->user(), $request->validatedForEvidence());

        return to_route('staff.billing-groups.show', $billingGroup);
    }
}
