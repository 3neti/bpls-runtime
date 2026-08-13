<?php

namespace App\Http\Controllers\Staff;

use App\Actions\RenderReceiptPdf;
use App\Actions\VoidReceipt;
use App\Enums\UserPermission;
use App\Exceptions\UnresolvedReceiptPolicy;
use App\Http\Controllers\Controller;
use App\Models\CollectionAllocation;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt): Response
    {
        Gate::authorize(UserPermission::ViewReceipts->value);

        $receipt->load([
            'issuedBy',
            'treasuryCollection.receivedBy',
            'treasuryCollection.allocations.paymentScheduleLine.lineOfBusiness',
            'paymentSchedule',
            'permitApplication.business.owner',
            'assessment',
        ]);

        return Inertia::render('receipts/Show', [
            'receipt' => $this->receiptPayload($receipt),
            'policyGaps' => [
                'Automatic receipt numbering authority remains unresolved.',
                'This is a print-friendly receipt view, not the final official PDF layout.',
                'Void, reprint, and reconciliation policy remain unresolved.',
            ],
            'can' => [
                'void_receipts' => auth()->user()?->can(UserPermission::VoidReceipts->value) ?? false,
            ],
        ]);
    }

    public function pdf(Receipt $receipt, RenderReceiptPdf $renderReceiptPdf): HttpResponse
    {
        Gate::authorize(UserPermission::ViewReceipts->value);

        return response($renderReceiptPdf->handle($receipt))
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->receiptFilename($receipt).'"',
            ]);
    }

    public function voidReceipt(Receipt $receipt, VoidReceipt $voidReceipt): RedirectResponse
    {
        Gate::authorize(UserPermission::VoidReceipts->value);

        try {
            $voidReceipt->handle($receipt, auth()->user());
        } catch (UnresolvedReceiptPolicy $exception) {
            return back()->withErrors([
                'receipt_policy' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(Receipt $receipt): array
    {
        $collection = $receipt->treasuryCollection;
        $permitApplication = $receipt->permitApplication;
        $business = $permitApplication->business;
        $businessOwner = $business->owner;

        return [
            'id' => $receipt->id,
            'status' => $receipt->status->value,
            'numbering_authority' => $receipt->numbering_authority,
            'receipt_number' => $receipt->receipt_number,
            'amount_cents' => $receipt->amount_cents,
            'issued_at' => $receipt->issued_at->toIso8601String(),
            'issued_by' => $receipt->issuedBy?->name,
            'remarks' => $receipt->remarks,
            'source_snapshot' => $receipt->source_snapshot,
            'collection' => [
                'id' => $collection->id,
                'status' => $collection->status->value,
                'channel' => $collection->channel->value,
                'method' => $collection->method->value,
                'amount_cents' => $collection->amount_cents,
                'payer_name' => $collection->payer_name,
                'reference_number' => $collection->reference_number,
                'received_at' => $collection->received_at->toIso8601String(),
                'received_by' => $collection->receivedBy?->name,
            ],
            'payment_schedule' => [
                'id' => $receipt->paymentSchedule->id,
                'sequence' => $receipt->paymentSchedule->sequence,
                'status' => $receipt->paymentSchedule->status->value,
                'payment_mode' => $receipt->paymentSchedule->payment_mode,
            ],
            'assessment' => [
                'id' => $receipt->assessment->id,
                'sequence' => $receipt->assessment->sequence,
                'status' => $receipt->assessment->status->value,
            ],
            'permit_application' => [
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'type' => $permitApplication->type->value,
                'status' => $permitApplication->status->value,
                'application_year' => $permitApplication->application_year,
            ],
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'trade_name' => $business->trade_name,
                'registration_number' => $business->registration_number,
                'address' => $business->address,
                'barangay' => $business->barangay,
                'owner' => [
                    'id' => $businessOwner->id,
                    'name' => $businessOwner->name,
                    'email' => $businessOwner->email,
                    'phone' => $businessOwner->phone,
                    'address' => $businessOwner->address,
                ],
            ],
            'allocations' => $collection->allocations
                ->values()
                ->map(fn (CollectionAllocation $allocation): array => [
                    'id' => $allocation->id,
                    'code' => $allocation->paymentScheduleLine->code,
                    'name' => $allocation->paymentScheduleLine->name,
                    'category' => $allocation->paymentScheduleLine->category->value,
                    'line_of_business' => $allocation->paymentScheduleLine->lineOfBusiness?->name,
                    'amount_cents' => $allocation->amount_cents,
                ]),
        ];
    }

    private function receiptFilename(Receipt $receipt): string
    {
        $safeReceiptNumber = str($receipt->receipt_number)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->lower()
            ->toString();

        return ($safeReceiptNumber === '' ? 'receipt-'.$receipt->id : $safeReceiptNumber).'.pdf';
    }
}
