<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateBillingGroupDraftRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreBillingGroupRecordRequest;
use App\Models\BillingGroup;
use Illuminate\Http\RedirectResponse;

class BillingGroupRecordController extends Controller
{
    public function store(StoreBillingGroupRecordRequest $request, BillingGroup $billingGroup, CreateBillingGroupDraftRecord $createDraftRecord): RedirectResponse
    {
        $createDraftRecord->handle($billingGroup, $request->user(), $request->validated());

        return to_route('staff.billing-groups.show', $billingGroup);
    }
}
