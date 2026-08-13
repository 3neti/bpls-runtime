<?php

namespace App\Http\Controllers\Staff;

use App\Actions\IssueManualCollectionReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreCollectionReceiptRequest;
use App\Models\TreasuryCollection;
use Illuminate\Http\RedirectResponse;

class CollectionReceiptController extends Controller
{
    public function store(
        StoreCollectionReceiptRequest $request,
        TreasuryCollection $collection,
        IssueManualCollectionReceipt $issueManualCollectionReceipt,
    ): RedirectResponse {
        $issueManualCollectionReceipt->handle(
            $collection,
            $request->validatedForReceipt(),
            $request->user(),
        );

        return to_route('staff.payment-schedules.show', $collection->payment_schedule_id);
    }
}
