<?php

namespace App\Http\Controllers\Staff;

use App\Actions\DescribeReceiptVoidBoundary;
use App\Actions\RenderReceiptPdf;
use App\Actions\VoidReceipt;
use App\Enums\ReceiptStatus;
use App\Enums\UserPermission;
use App\Exceptions\UnresolvedReceiptPolicy;
use App\Http\Controllers\Controller;
use App\Models\CollectionAllocation;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly DescribeReceiptVoidBoundary $describeVoidBoundary,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewReceipts->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ReceiptStatus::class)],
        ]);
        $search = str($filters['q'] ?? '')->trim()->toString();
        $status = $filters['status'] ?? null;

        $receipts = Receipt::query()
            ->with(['issuedBy', 'treasuryCollection', 'paymentSchedule', 'permitApplication.business.owner'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('receipt_number', 'like', '%'.$search.'%')
                        ->orWhereHas('treasuryCollection', function ($query) use ($search): void {
                            $query
                                ->where('payer_name', 'like', '%'.$search.'%')
                                ->orWhere('reference_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('permitApplication', function ($query) use ($search): void {
                            $query->where('application_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('permitApplication.business', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('trade_name', 'like', '%'.$search.'%')
                                ->orWhere('registration_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('permitApplication.business.owner', function ($query) use ($search): void {
                            $query->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Receipt $receipt): array => $this->receiptQueuePayload($receipt));

        return Inertia::render('receipts/Index', [
            'receipts' => $receipts,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statuses' => collect(ReceiptStatus::cases())
                ->map(fn (ReceiptStatus $status): array => [
                    'label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'value' => $status->value,
                ])
                ->values(),
        ]);
    }

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
    private function receiptQueuePayload(Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'status' => $receipt->status->value,
            'numbering_authority' => $receipt->numbering_authority,
            'receipt_number' => $receipt->receipt_number,
            'amount_cents' => $receipt->amount_cents,
            'issued_at' => $receipt->issued_at->toIso8601String(),
            'issued_by' => $receipt->issuedBy?->name,
            'collection' => [
                'id' => $receipt->treasuryCollection->id,
                'status' => $receipt->treasuryCollection->status->value,
                'method' => $receipt->treasuryCollection->method->value,
                'payer_name' => $receipt->treasuryCollection->payer_name,
                'reference_number' => $receipt->treasuryCollection->reference_number,
            ],
            'payment_schedule' => [
                'id' => $receipt->paymentSchedule->id,
                'sequence' => $receipt->paymentSchedule->sequence,
                'status' => $receipt->paymentSchedule->status->value,
            ],
            'permit_application' => [
                'id' => $receipt->permitApplication->id,
                'application_number' => $receipt->permitApplication->application_number,
                'status' => $receipt->permitApplication->status->value,
                'application_year' => $receipt->permitApplication->application_year,
                'business_name' => $receipt->permitApplication->business->name,
                'trade_name' => $receipt->permitApplication->business->trade_name,
                'owner_name' => $receipt->permitApplication->business->owner->name,
            ],
        ];
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
            'void_boundary' => $this->describeVoidBoundary->handle($receipt),
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
