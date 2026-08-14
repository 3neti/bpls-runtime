<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreatePaymentScheduleForAssessment;
use App\Enums\PaymentScheduleStatus;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\PaymentSchedule;
use App\Models\TreasuryCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentPaymentScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewPaymentSchedules->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PaymentScheduleStatus::class)],
        ]);
        $search = str($filters['q'] ?? '')->trim()->toString();
        $status = $filters['status'] ?? null;

        $paymentSchedules = PaymentSchedule::query()
            ->with(['assessment', 'permitApplication.business.owner'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('sequence', $search)
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
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PaymentSchedule $paymentSchedule): array => $this->paymentScheduleQueuePayload($paymentSchedule));

        return Inertia::render('payment-schedules/Index', [
            'paymentSchedules' => $paymentSchedules,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statuses' => collect(PaymentScheduleStatus::cases())
                ->map(fn (PaymentScheduleStatus $status): array => [
                    'label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'value' => $status->value,
                ])
                ->values(),
        ]);
    }

    public function store(Assessment $assessment, CreatePaymentScheduleForAssessment $createPaymentSchedule): RedirectResponse
    {
        Gate::authorize(UserPermission::PreparePaymentSchedules->value);

        $paymentSchedule = $createPaymentSchedule->handle($assessment, auth()->user());

        return to_route('staff.payment-schedules.show', $paymentSchedule);
    }

    public function show(PaymentSchedule $paymentSchedule): Response
    {
        Gate::authorize(UserPermission::ViewPaymentSchedules->value);

        $canRecordCollections = auth()->user()?->can(UserPermission::RecordCollections->value) ?? false;
        $canViewCollections = auth()->user()?->can(UserPermission::ViewCollections->value) ?? false;
        $canIssueReceipts = auth()->user()?->can(UserPermission::IssueReceipts->value) ?? false;
        $canViewReceipts = auth()->user()?->can(UserPermission::ViewReceipts->value) ?? false;

        $paymentSchedule->load([
            'preparedBy',
            'assessment',
            'permitApplication.business.owner',
            'lines.lineOfBusiness',
        ]);

        if ($canRecordCollections || $canViewCollections || $canIssueReceipts || $canViewReceipts) {
            $paymentSchedule->load([
                'treasuryCollections' => fn ($query) => $query
                    ->with(['receivedBy', 'allocations.paymentScheduleLine'])
                    ->when(
                        $canIssueReceipts || $canViewReceipts,
                        fn ($query) => $query->with(['receipt.issuedBy']),
                    )
                    ->latest('received_at'),
            ]);
        }

        return Inertia::render('payment-schedules/Show', [
            'paymentSchedule' => $this->paymentSchedulePayload($paymentSchedule),
            'collectionMethods' => collect(TreasuryCollectionMethod::cases())
                ->map(fn (TreasuryCollectionMethod $method): array => [
                    'label' => str($method->value)->replace('_', ' ')->title()->toString(),
                    'value' => $method->value,
                ])
                ->values(),
            'can' => [
                'record_collections' => $canRecordCollections,
                'view_collections' => $canViewCollections,
                'issue_receipts' => $canIssueReceipts,
                'view_receipts' => $canViewReceipts,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentScheduleQueuePayload(PaymentSchedule $paymentSchedule): array
    {
        return [
            'id' => $paymentSchedule->id,
            'sequence' => $paymentSchedule->sequence,
            'status' => $paymentSchedule->status->value,
            'payment_mode' => $paymentSchedule->payment_mode,
            'due_on' => $paymentSchedule->due_on?->toDateString(),
            'total_amount_cents' => $paymentSchedule->total_amount_cents,
            'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'created_at' => $paymentSchedule->created_at?->toIso8601String(),
            'assessment' => [
                'id' => $paymentSchedule->assessment->id,
                'sequence' => $paymentSchedule->assessment->sequence,
                'status' => $paymentSchedule->assessment->status->value,
            ],
            'permit_application' => [
                'id' => $paymentSchedule->permitApplication->id,
                'application_number' => $paymentSchedule->permitApplication->application_number,
                'status' => $paymentSchedule->permitApplication->status->value,
                'application_year' => $paymentSchedule->permitApplication->application_year,
                'business_name' => $paymentSchedule->permitApplication->business->name,
                'trade_name' => $paymentSchedule->permitApplication->business->trade_name,
                'owner_name' => $paymentSchedule->permitApplication->business->owner->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSchedulePayload(PaymentSchedule $paymentSchedule): array
    {
        $treasuryCollections = $paymentSchedule->relationLoaded('treasuryCollections')
            ? $paymentSchedule->treasuryCollections
            : collect();

        return [
            'id' => $paymentSchedule->id,
            'sequence' => $paymentSchedule->sequence,
            'status' => $paymentSchedule->status->value,
            'payment_mode' => $paymentSchedule->payment_mode,
            'due_on' => $paymentSchedule->due_on?->toDateString(),
            'total_amount_cents' => $paymentSchedule->total_amount_cents,
            'paid_amount_cents' => $paymentSchedule->paid_amount_cents,
            'prepared_by' => $paymentSchedule->preparedBy?->name,
            'created_at' => $paymentSchedule->created_at?->toIso8601String(),
            'assessment' => [
                'id' => $paymentSchedule->assessment->id,
                'sequence' => $paymentSchedule->assessment->sequence,
                'status' => $paymentSchedule->assessment->status->value,
            ],
            'permit_application' => [
                'id' => $paymentSchedule->permitApplication->id,
                'application_number' => $paymentSchedule->permitApplication->application_number,
                'type' => $paymentSchedule->permitApplication->type->value,
                'status' => $paymentSchedule->permitApplication->status->value,
                'application_year' => $paymentSchedule->permitApplication->application_year,
                'business_name' => $paymentSchedule->permitApplication->business->name,
                'owner_name' => $paymentSchedule->permitApplication->business->owner->name,
            ],
            'lines' => $paymentSchedule->lines
                ->sortBy('code')
                ->values()
                ->map(fn ($line): array => [
                    'id' => $line->id,
                    'assessment_line_id' => $line->assessment_line_id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'category' => $line->category->value,
                    'due_on' => $line->due_on?->toDateString(),
                    'status' => $line->status->value,
                    'amount_cents' => $line->amount_cents,
                    'paid_amount_cents' => $line->paid_amount_cents,
                    'line_of_business' => $line->lineOfBusiness?->name,
                ]),
            'collections' => $treasuryCollections
                ->values()
                ->map(fn (TreasuryCollection $collection): array => [
                    'id' => $collection->id,
                    'status' => $collection->status->value,
                    'channel' => $collection->channel->value,
                    'method' => $collection->method->value,
                    'amount_cents' => $collection->amount_cents,
                    'payer_name' => $collection->payer_name,
                    'reference_number' => $collection->reference_number,
                    'received_at' => $collection->received_at->toIso8601String(),
                    'received_by' => $collection->receivedBy?->name,
                    'receipt' => $collection->relationLoaded('receipt') && $collection->receipt !== null ? [
                        'id' => $collection->receipt->id,
                        'status' => $collection->receipt->status->value,
                        'numbering_authority' => $collection->receipt->numbering_authority,
                        'receipt_number' => $collection->receipt->receipt_number,
                        'amount_cents' => $collection->receipt->amount_cents,
                        'issued_at' => $collection->receipt->issued_at->toIso8601String(),
                        'issued_by' => $collection->receipt->issuedBy?->name,
                    ] : null,
                    'allocations' => $collection->allocations
                        ->values()
                        ->map(fn ($allocation): array => [
                            'id' => $allocation->id,
                            'payment_schedule_line_id' => $allocation->payment_schedule_line_id,
                            'code' => $allocation->paymentScheduleLine->code,
                            'name' => $allocation->paymentScheduleLine->name,
                            'amount_cents' => $allocation->amount_cents,
                        ]),
                ]),
        ];
    }
}
