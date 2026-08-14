<?php

namespace App\Actions;

use App\Models\Receipt;

final class DescribeReceiptVoidBoundary
{
    /**
     * @return array{
     *     reference: string,
     *     status: string,
     *     can_void: bool,
     *     receipt_status: string,
     *     collection_status: string,
     *     policy_note: string
     * }
     */
    public function handle(Receipt $receipt): array
    {
        return [
            'reference' => $this->reference($receipt),
            'status' => 'blocked',
            'can_void' => false,
            'receipt_status' => $receipt->status->value,
            'collection_status' => $receipt->treasuryCollection->status->value,
            'policy_note' => 'Receipt void, reversal, receipt-number reuse, and reconciliation policy remain unresolved; void attempts are refused without mutating financial state.',
        ];
    }

    private function reference(Receipt $receipt): string
    {
        $source = implode('|', [
            $receipt->id,
            $receipt->receipt_number,
            $receipt->payment_schedule_id,
            $receipt->treasury_collection_id,
            $receipt->issued_at?->toIso8601String() ?? '',
        ]);

        return 'RVB-'.$receipt->id.'-'.substr(hash('sha256', $source), 0, 16);
    }
}
