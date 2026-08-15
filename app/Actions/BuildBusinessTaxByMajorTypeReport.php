<?php

namespace App\Actions;

use App\Enums\FeeRuleCategory;
use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\CollectionAllocation;
use App\Models\LineOfBusiness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildBusinessTaxByMajorTypeReport
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, receipt_from?: string|null, receipt_to?: string|null}  $filters
     * @return array{
     *     filters: array{date_from: string|null, date_to: string|null, receipt_from: string|null, receipt_to: string|null},
     *     summary: array<string, mixed>,
     *     rows: Collection<int, array{major_type: string, allocation_count: int, receipt_count: int, amount_cents: int}>
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
        $receiptFrom = filled($filters['receipt_from'] ?? null) ? trim((string) $filters['receipt_from']) : null;
        $receiptTo = filled($filters['receipt_to'] ?? null) ? trim((string) $filters['receipt_to']) : null;

        $allocations = CollectionAllocation::query()
            ->with([
                'paymentScheduleLine',
                'treasuryCollection.receipt',
                'treasuryCollection.permitApplication.lines' => fn ($query) => $query->orderBy('id'),
                'treasuryCollection.permitApplication.lines.lineOfBusiness',
            ])
            ->whereHas('paymentScheduleLine', fn ($query) => $query->where('category', FeeRuleCategory::Tax))
            ->whereHas('treasuryCollection', function ($query) use ($dateFrom, $dateTo, $receiptFrom, $receiptTo): void {
                $query
                    ->where('status', TreasuryCollectionStatus::Receipted)
                    ->when($dateFrom !== null, fn ($query) => $query->where('received_at', '>=', $dateFrom))
                    ->when($dateTo !== null, fn ($query) => $query->where('received_at', '<=', $dateTo))
                    ->whereHas('receipt', function ($query) use ($receiptFrom, $receiptTo): void {
                        $query
                            ->where('status', ReceiptStatus::Issued)
                            ->when($receiptFrom !== null, fn ($query) => $query->where('receipt_number', '>=', $receiptFrom))
                            ->when($receiptTo !== null, fn ($query) => $query->where('receipt_number', '<=', $receiptTo));
                    });
            })
            ->get();

        $collected = $allocations->map(fn (CollectionAllocation $allocation): array => [
            'major_type' => $this->primaryMajorType($allocation),
            'amount_cents' => $allocation->amount_cents,
            'receipt_id' => $allocation->treasuryCollection->receipt?->id,
        ]);
        $amountsByMajor = $collected->groupBy('major_type');
        $knownMajorTypes = LineOfBusiness::query()
            ->whereNotNull('major_category')
            ->where('major_category', '!=', '')
            ->distinct()
            ->orderBy('major_category')
            ->pluck('major_category');
        $majorTypes = $knownMajorTypes
            ->when($amountsByMajor->has('Unclassified'), fn (Collection $majorTypes) => $majorTypes->push('Unclassified'))
            ->unique()
            ->values();
        $rows = $majorTypes->map(function (string $majorType) use ($amountsByMajor): array {
            $majorCollections = $amountsByMajor->get($majorType, collect());
            $amountCents = $majorCollections->reduce(
                fn (int $total, array $collection): int => $total + $collection['amount_cents'],
                0,
            );

            return [
                'major_type' => $majorType,
                'allocation_count' => $majorCollections->count(),
                'receipt_count' => $majorCollections->pluck('receipt_id')->filter()->unique()->count(),
                'amount_cents' => $amountCents,
            ];
        });

        return [
            'filters' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'receipt_from' => $receiptFrom,
                'receipt_to' => $receiptTo,
            ],
            'summary' => [
                'major_type_count' => $rows->count(),
                'collected_major_type_count' => $rows->where('amount_cents', '>', 0)->count(),
                'allocation_count' => $allocations->count(),
                'receipt_count' => $collected->pluck('receipt_id')->filter()->unique()->count(),
                'total_amount_cents' => $rows->reduce(
                    fn (int $total, array $row): int => $total + $row['amount_cents'],
                    0,
                ),
                'date_basis' => 'treasury_collection_received_at',
                'classification_basis' => 'first_permit_application_line_major_category',
                'scope' => 'Receipted business-tax collection allocations with issued receipts, grouped by the first declared business activity major category.',
                'policy_note' => 'Amounts are collected allocations from persisted Tax schedule lines only. The report excludes fee allocations, surcharge, interest, penalty, PIL, assessed-but-uncollected liability, pending receipts, and voided evidence; it does not recalculate tax.',
                'classification_note' => 'Applications without a usable first-activity major category remain visible as Unclassified. Major categories with no matching collections remain visible at zero for legacy report continuity.',
            ],
            'rows' => $rows,
        ];
    }

    private function primaryMajorType(CollectionAllocation $allocation): string
    {
        $primaryLine = $allocation->treasuryCollection->permitApplication->lines->first();
        $majorType = $primaryLine?->lineOfBusiness?->major_category;

        return filled($majorType) ? $majorType : 'Unclassified';
    }
}
