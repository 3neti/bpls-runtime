<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\TreasuryCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BuildDailyCollectionsReport
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{
     *     filters: array{date_from: string, date_to: string},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? $dateFrom->toDateString())->endOfDay();

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $collections = TreasuryCollection::query()
            ->with([
                'receipt.issuedBy',
                'receivedBy',
                'permitApplication.business.owner',
                'paymentSchedule',
            ])
            ->where('status', TreasuryCollectionStatus::Receipted)
            ->whereBetween('received_at', [$dateFrom, $dateTo])
            ->whereHas('receipt', fn ($query) => $query->where('status', ReceiptStatus::Issued))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $rows = $collections
            ->map(fn (TreasuryCollection $collection): array => $this->row($collection))
            ->values();

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'summary' => [
                'row_count' => $rows->count(),
                'total_amount_cents' => $rows->sum('amount_cents'),
                'cash_amount_cents' => $rows
                    ->where('method', 'cash')
                    ->sum('amount_cents'),
                'manual_receipt_count' => $rows
                    ->where('numbering_authority', 'manual')
                    ->count(),
                'date_basis' => 'collection_received_at',
                'scope' => 'Receipted permit collections with issued receipts only.',
                'policy_note' => 'Official report cutoff, non-permit Treasury billing groups, void/reversal handling, and abstract formats remain explicit reporting acceptance questions.',
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(TreasuryCollection $collection): array
    {
        $receipt = $collection->receipt;
        $permitApplication = $collection->permitApplication;
        $business = $permitApplication->business;

        return [
            'collection_id' => $collection->id,
            'received_at' => $collection->received_at->toIso8601String(),
            'receipt_id' => $receipt?->id,
            'receipt_number' => $receipt?->receipt_number,
            'receipt_issued_at' => $receipt?->issued_at?->toIso8601String(),
            'numbering_authority' => $receipt?->numbering_authority,
            'application_id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'owner_name' => $business->owner->name,
            'payer_name' => $collection->payer_name,
            'reference_number' => $collection->reference_number,
            'method' => $collection->method->value,
            'channel' => $collection->channel->value,
            'collection_status' => $collection->status->value,
            'receipt_status' => $receipt?->status->value,
            'amount_cents' => $collection->amount_cents,
            'received_by' => $collection->receivedBy?->name,
            'issued_by' => $receipt?->issuedBy?->name,
        ];
    }
}
