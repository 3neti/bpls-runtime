<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\PaymentSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BuildPaymentSummaryReport
{
    /**
     * @param  array{year?: int|string|null, type?: string|null, status?: string|null, q?: string|null}  $filters
     * @return array{
     *     filters: array{year: int, type: string|null, status: string|null, q: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $type = filled($filters['type'] ?? null) ? (string) $filters['type'] : null;
        $status = filled($filters['status'] ?? null) ? (string) $filters['status'] : null;
        $search = filled($filters['q'] ?? null) ? trim((string) $filters['q']) : null;

        $schedules = PaymentSchedule::query()
            ->with([
                'permitApplication.business.owner',
                'treasuryCollections.receipt',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->whereHas('permitApplication', function ($query) use ($year, $type, $search): void {
                $query->where('application_year', $year)
                    ->when($type !== null, fn ($query) => $query->where('type', $type))
                    ->when($search !== null, function ($query) use ($search): void {
                        $query->where(function ($query) use ($search): void {
                            $query
                                ->where('application_number', 'like', '%'.$search.'%')
                                ->orWhereHas('business', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('trade_name', 'like', '%'.$search.'%')
                                        ->orWhere('registration_number', 'like', '%'.$search.'%')
                                        ->orWhereHas('owner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                                });
                        });
                    });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $rows = $schedules
            ->map(fn (PaymentSchedule $schedule): array => $this->row($schedule))
            ->values();
        $activeRows = $rows->where('is_financially_active', true);

        return [
            'filters' => [
                'year' => $year,
                'type' => $type,
                'status' => $status,
                'q' => $search,
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'business_count' => $rows->pluck('business_id')->unique()->count(),
                'pending_count' => $rows->where('schedule_status', PaymentScheduleStatus::Pending->value)->count(),
                'partially_paid_count' => $rows->where('schedule_status', PaymentScheduleStatus::PartiallyPaid->value)->count(),
                'paid_count' => $rows->where('schedule_status', PaymentScheduleStatus::Paid->value)->count(),
                'voided_count' => $rows->where('schedule_status', PaymentScheduleStatus::Voided->value)->count(),
                'total_amount_cents' => $activeRows->sum('total_amount_cents'),
                'paid_amount_cents' => $activeRows->sum('paid_amount_cents'),
                'outstanding_amount_cents' => $activeRows->sum('outstanding_amount_cents'),
                'receipted_amount_cents' => $activeRows->sum('receipted_amount_cents'),
                'pending_receipt_amount_cents' => $activeRows->sum('pending_receipt_amount_cents'),
                'date_basis' => 'permit_application_year',
                'grain' => 'one_row_per_payment_schedule',
                'scope' => 'Permit payment schedules and their persisted collection and receipt evidence for the selected application year.',
                'policy_note' => 'This report presents persisted financial statuses without recalculating liability or inferring reconciliation, legal delinquency, receipt validity beyond recorded status, surcharge, interest, PIL, or official report acceptance.',
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function row(PaymentSchedule $schedule): array
    {
        $permitApplication = $schedule->permitApplication;
        $business = $permitApplication->business;
        $activeCollections = $schedule->treasuryCollections
            ->where('status', '!=', TreasuryCollectionStatus::Voided);
        $issuedReceipts = $activeCollections
            ->pluck('receipt')
            ->filter()
            ->where('status', ReceiptStatus::Issued);
        $latestReceipt = $issuedReceipts->sortBy('issued_at')->last();
        $isFinanciallyActive = $schedule->status !== PaymentScheduleStatus::Voided;
        $collectionAmountCents = $activeCollections->sum('amount_cents');

        return [
            'payment_schedule_id' => $schedule->id,
            'schedule_sequence' => $schedule->sequence,
            'schedule_status' => $schedule->status->value,
            'payment_mode' => $schedule->payment_mode,
            'due_on' => $schedule->due_on?->toDateString(),
            'is_financially_active' => $isFinanciallyActive,
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'application_status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $business->id,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'registration_number' => $business->registration_number,
            'owner_name' => $business->owner->name,
            'total_amount_cents' => $schedule->total_amount_cents,
            'paid_amount_cents' => $schedule->paid_amount_cents,
            'outstanding_amount_cents' => max(0, $schedule->total_amount_cents - $schedule->paid_amount_cents),
            'collection_amount_cents' => $collectionAmountCents,
            'collection_difference_cents' => $schedule->paid_amount_cents - $collectionAmountCents,
            'collection_count' => $activeCollections->count(),
            'receipted_amount_cents' => $issuedReceipts->sum('amount_cents'),
            'receipted_count' => $issuedReceipts->count(),
            'pending_receipt_amount_cents' => $activeCollections
                ->where('status', TreasuryCollectionStatus::PendingReceipt)
                ->sum('amount_cents'),
            'pending_receipt_count' => $activeCollections
                ->where('status', TreasuryCollectionStatus::PendingReceipt)
                ->count(),
            'collection_methods' => $activeCollections
                ->map(fn ($collection): string => $collection->method->value)
                ->unique()
                ->values()
                ->all(),
            'latest_receipt_number' => $latestReceipt?->receipt_number,
            'latest_receipt_issued_at' => $latestReceipt?->issued_at instanceof Carbon
                ? $latestReceipt->issued_at->toIso8601String()
                : null,
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }
}
