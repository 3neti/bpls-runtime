<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Models\PaymentSchedule;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use Illuminate\Support\Collection;

final class BuildPaymentScheduleReceiptReconciliation
{
    /**
     * @return array{
     *     collected_amount_cents: int,
     *     required_receipt_group_count: int,
     *     issued_receipt_group_count: int,
     *     receipt_groups: array<int, array{key: string|null, label: string|null, allocated_amount_cents: int, receipt_issued: bool, receipt_id: int|null}>,
     *     receipt_count: int,
     *     total_receipted_cents: int,
     *     unreceipted_amount_cents: int,
     *     receipt_coverage_complete: bool,
     *     totals_reconciled: bool,
     *     status: string,
     *     synthetic: bool
     * }
     */
    public function handle(PaymentSchedule $schedule): array
    {
        $schedule->loadMissing(['treasuryCollections.receipts', 'treasuryCollections.allocations']);
        $scheduleCollections = $schedule->treasuryCollections;
        $issuedReceipts = $scheduleCollections
            ->flatMap(fn (TreasuryCollection $collection) => $collection->receipts)
            ->filter(fn (Receipt $receipt): bool => $receipt->status === ReceiptStatus::Issued);
        $requiredReceiptGroups = $scheduleCollections
            ->flatMap(fn (TreasuryCollection $collection) => $collection->allocations)
            ->pluck('receipt_group_key')
            ->filter()
            ->unique()
            ->values();
        $issuedReceiptGroups = $issuedReceipts
            ->pluck('receipt_group_key')
            ->filter()
            ->unique()
            ->values();
        $receiptGroups = $scheduleCollections
            ->flatMap(fn (TreasuryCollection $collection) => $collection->allocations)
            ->groupBy('receipt_group_key')
            ->map(function (Collection $allocations, string $receiptGroupKey) use ($issuedReceipts): array {
                $receipt = $issuedReceipts->firstWhere('receipt_group_key', $receiptGroupKey);

                return [
                    'key' => $receiptGroupKey,
                    'label' => (string) $allocations->first()?->receipt_group_label,
                    'allocated_amount_cents' => (int) $allocations->sum('amount_cents'),
                    'receipt_issued' => $receipt instanceof Receipt,
                    'receipt_id' => $receipt?->id,
                ];
            })
            ->values();
        if ($receiptGroups->isEmpty() && $issuedReceipts->isNotEmpty()) {
            $receiptGroups = $issuedReceipts->map(fn (Receipt $receipt): array => [
                'key' => $receipt->receipt_group_key,
                'label' => $receipt->receipt_group_label,
                'allocated_amount_cents' => $receipt->amount_cents,
                'receipt_issued' => true,
                'receipt_id' => $receipt->id,
            ])->values();
        }
        $collectionTotalCents = (int) $scheduleCollections->sum('amount_cents');
        $totalReceiptedCents = (int) $issuedReceipts->sum('amount_cents');
        $requiredReceiptGroupCount = $requiredReceiptGroups->count();
        if ($scheduleCollections->isNotEmpty() && $requiredReceiptGroupCount === 0) {
            $requiredReceiptGroupCount = 1;
        }
        $issuedReceiptGroupCount = $issuedReceiptGroups->count();
        if ($issuedReceipts->isNotEmpty() && $issuedReceiptGroupCount === 0) {
            $issuedReceiptGroupCount = $issuedReceipts->count();
        }
        $receiptCoverageComplete = $scheduleCollections->isNotEmpty()
            && $issuedReceipts->isNotEmpty()
            && $scheduleCollections->every(fn (TreasuryCollection $collection): bool => $collection->allocations->isEmpty()
                ? $collection->receipts->isNotEmpty()
                : $collection->allocations->every(fn ($allocation): bool => $allocation->receipt_id !== null));
        $totalsReconciled = $receiptCoverageComplete
            && $collectionTotalCents === $schedule->paid_amount_cents
            && $totalReceiptedCents === $collectionTotalCents;
        $reconciliationStatus = match (true) {
            $scheduleCollections->isEmpty() => 'awaiting_collection',
            $totalsReconciled => 'fully_reconciled',
            $totalReceiptedCents > $collectionTotalCents => 'mismatch',
            default => 'pending_receipts',
        };
        $syntheticPayment = $scheduleCollections->contains(
            fn (TreasuryCollection $collection): bool => data_get($collection->source_snapshot, 'integration_evidence.synthetic_only') === true,
        );

        return [
            'collected_amount_cents' => $collectionTotalCents,
            'required_receipt_group_count' => $requiredReceiptGroupCount,
            'issued_receipt_group_count' => $issuedReceiptGroupCount,
            'receipt_groups' => $receiptGroups->all(),
            'receipt_count' => $issuedReceipts->count(),
            'total_receipted_cents' => $totalReceiptedCents,
            'unreceipted_amount_cents' => max(0, $collectionTotalCents - $totalReceiptedCents),
            'receipt_coverage_complete' => $receiptCoverageComplete,
            'totals_reconciled' => $totalsReconciled,
            'status' => $reconciliationStatus,
            'synthetic' => $syntheticPayment,
        ];
    }
}
