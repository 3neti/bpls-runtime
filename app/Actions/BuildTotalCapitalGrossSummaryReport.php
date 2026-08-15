<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\PermitApplication;
use App\Models\TreasuryCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BuildTotalCapitalGrossSummaryReport
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{
     *     filters: array{date_from: string|null, date_to: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $dateFrom = filled($filters['date_from'] ?? null)
            ? Carbon::parse((string) $filters['date_from'])->startOfDay()
            : null;
        $dateTo = filled($filters['date_to'] ?? null)
            ? Carbon::parse((string) $filters['date_to'])->endOfDay()
            : null;

        $permitApplications = PermitApplication::query()
            ->with([
                'business.owner',
                'lines',
                'paymentSchedules',
                'treasuryCollections.paymentSchedule',
                'treasuryCollections.receipt',
            ])
            ->whereHas('treasuryCollections', function ($query) use ($dateFrom, $dateTo): void {
                $query
                    ->where('status', TreasuryCollectionStatus::Receipted)
                    ->when($dateFrom !== null, fn ($query) => $query->where('received_at', '>=', $dateFrom))
                    ->when($dateTo !== null, fn ($query) => $query->where('received_at', '<=', $dateTo))
                    ->whereHas('paymentSchedule', fn ($query) => $query->where('status', '!=', PaymentScheduleStatus::Voided))
                    ->whereHas('receipt', fn ($query) => $query->where('status', ReceiptStatus::Issued));
            })
            ->get();

        $rows = $permitApplications
            ->map(fn (PermitApplication $permitApplication): array => $this->row($permitApplication))
            ->sortBy([
                ['owner_name', 'asc'],
                ['business_name', 'asc'],
            ])
            ->values();

        return [
            'filters' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'business_count' => $rows->pluck('business_id')->unique()->count(),
                'capital_investment_cents' => $rows->sum('capital_investment_cents'),
                'gross_sales_cents' => $rows->sum('gross_sales_cents'),
                'payment_amount_cents' => $rows->sum('payment_amount_cents'),
                'remaining_balance_cents' => $rows->sum('remaining_balance_cents'),
                'completed_count' => $rows->where('payment_status', 'Completed')->count(),
                'partial_count' => $rows->where('payment_status', 'Partial')->count(),
                'qualification_date_basis' => 'treasury_collection_received_at',
                'financial_scope' => 'lifetime_issued_receipted_collections',
                'grain' => 'one_row_per_permit_application',
                'scope' => 'One row per permit application with an issued, receipted collection in the selected collection-date range.',
                'policy_note' => 'Capital and gross sales are summed once from persisted application declarations. Payment amount is the lifetime total of issued, receipted collections on non-voided schedules; remaining balance is persisted schedule liability less those collections. The report does not recalculate assessment, infer receipt validity beyond recorded status, or resolve reversals, reconciliation, surcharge, interest, PIL, or official report acceptance.',
                'legacy_note' => 'The date range qualifies which applications appear; declaration, payment, balance, and latest receipt columns remain lifetime figures, matching the legacy report contract.',
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function row(PermitApplication $permitApplication): array
    {
        $activeSchedules = $permitApplication->paymentSchedules
            ->where('status', '!=', PaymentScheduleStatus::Voided);
        $activeScheduleIds = $activeSchedules->pluck('id');
        $receiptedCollections = $permitApplication->treasuryCollections
            ->whereIn('payment_schedule_id', $activeScheduleIds)
            ->where('status', TreasuryCollectionStatus::Receipted)
            ->filter(fn (TreasuryCollection $collection): bool => $collection->receipt?->status === ReceiptStatus::Issued);
        $latestCollection = $receiptedCollections
            ->sortBy([
                ['received_at', 'asc'],
                ['id', 'asc'],
            ])
            ->last();
        $paymentAmountCents = $receiptedCollections->sum('amount_cents');
        $totalLiabilityCents = $activeSchedules->sum('total_amount_cents');
        $remainingBalanceCents = max(0, $totalLiabilityCents - $paymentAmountCents);
        $business = $permitApplication->business;

        return [
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'application_type' => $permitApplication->type->value,
            'application_year' => $permitApplication->application_year,
            'business_id' => $business->id,
            'owner_name' => $business->owner->name,
            'business_name' => $business->name,
            'capital_investment_cents' => $permitApplication->lines->sum('capital_investment_cents'),
            'gross_sales_cents' => $permitApplication->lines->sum('declared_gross_sales_cents'),
            'latest_receipt_number' => $latestCollection?->receipt?->receipt_number,
            'latest_payment_date' => $latestCollection?->received_at->toDateString(),
            'payment_amount_cents' => $paymentAmountCents,
            'remaining_balance_cents' => $remainingBalanceCents,
            'payment_status' => $remainingBalanceCents === 0 ? 'Completed' : 'Partial',
        ];
    }
}
