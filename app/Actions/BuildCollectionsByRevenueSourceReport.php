<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\CollectionAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BuildCollectionsByRevenueSourceReport
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, category?: string|null}  $filters
     * @return array{
     *     filters: array{date_from: string, date_to: string, category: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function handle(array $filters = []): array
    {
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'] ?? $dateFrom->toDateString())->endOfDay();
        $category = filled($filters['category'] ?? null) ? (string) $filters['category'] : null;

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $allocations = CollectionAllocation::query()
            ->with([
                'paymentScheduleLine.lineOfBusiness',
                'treasuryCollection.receipt',
            ])
            ->whereHas('treasuryCollection', function ($query) use ($dateFrom, $dateTo): void {
                $query
                    ->where('status', TreasuryCollectionStatus::Receipted)
                    ->whereBetween('received_at', [$dateFrom, $dateTo])
                    ->whereHas('receipt', fn ($query) => $query->where('status', ReceiptStatus::Issued));
            })
            ->when($category !== null, fn ($query) => $query->whereHas('paymentScheduleLine', fn ($query) => $query->where('category', $category)))
            ->get();

        $rows = $allocations
            ->groupBy(fn (CollectionAllocation $allocation): string => $this->groupKey($allocation))
            ->map(fn (Collection $group): array => $this->row($group))
            ->sortBy([
                ['category', 'asc'],
                ['code', 'asc'],
            ])
            ->values();

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'category' => $category,
            ],
            'summary' => [
                'source_count' => $rows->count(),
                'allocation_count' => $allocations->count(),
                'total_amount_cents' => $rows->sum('amount_cents'),
                'date_basis' => 'collection_received_at',
                'scope' => 'Receipted permit collection allocations with issued receipts only.',
                'policy_note' => 'Official revenue account codes, fund/source-of-income mapping, non-permit billing groups, void/reversal treatment, and abstract formats remain explicit reporting acceptance questions.',
            ],
            'rows' => $rows,
        ];
    }

    private function groupKey(CollectionAllocation $allocation): string
    {
        $line = $allocation->paymentScheduleLine;

        return implode('|', [
            $line->category->value,
            $line->code,
            $line->name,
            $line->line_of_business_id ?? 'none',
        ]);
    }

    /**
     * @param  Collection<int, CollectionAllocation>  $allocations
     * @return array<string, mixed>
     */
    private function row(Collection $allocations): array
    {
        $firstAllocation = $allocations->first();
        $line = $firstAllocation->paymentScheduleLine;

        return [
            'category' => $line->category->value,
            'code' => $line->code,
            'name' => $line->name,
            'line_of_business_id' => $line->line_of_business_id,
            'line_of_business' => $line->lineOfBusiness?->name,
            'allocation_count' => $allocations->count(),
            'receipt_count' => $allocations
                ->map(fn (CollectionAllocation $allocation): ?int => $allocation->treasuryCollection->receipt?->id)
                ->filter()
                ->unique()
                ->count(),
            'amount_cents' => $allocations->sum('amount_cents'),
        ];
    }
}
