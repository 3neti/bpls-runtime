<?php

namespace App\Actions;

use App\Enums\ReceiptStatus;
use App\Enums\TreasuryCollectionStatus;
use App\Models\Receipt;
use App\Models\TreasuryCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class IssueManualCollectionReceipt
{
    /**
     * @param  array{receipt_number: string, numbering_authority: string, remarks?: string|null}  $data
     */
    public function handle(TreasuryCollection $collection, array $data, ?User $issuedBy = null): Receipt
    {
        return DB::transaction(function () use ($collection, $data, $issuedBy): Receipt {
            $collection = TreasuryCollection::query()
                ->whereKey($collection->id)
                ->with(['paymentSchedule', 'permitApplication.business.owner', 'assessment', 'receipt'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($collection->receipt instanceof Receipt) {
                return $collection->receipt->load(['issuedBy', 'treasuryCollection']);
            }

            if ($collection->status !== TreasuryCollectionStatus::PendingReceipt) {
                throw new LogicException("Receipt cannot be issued for collection [{$collection->id}] with status [{$collection->status->value}].");
            }

            $receipt = Receipt::query()->create([
                'treasury_collection_id' => $collection->id,
                'payment_schedule_id' => $collection->payment_schedule_id,
                'permit_application_id' => $collection->permit_application_id,
                'assessment_id' => $collection->assessment_id,
                'issued_by_id' => $issuedBy?->id,
                'status' => ReceiptStatus::Issued,
                'numbering_authority' => $data['numbering_authority'],
                'receipt_number' => $data['receipt_number'],
                'amount_cents' => $collection->amount_cents,
                'issued_at' => now(),
                'remarks' => $data['remarks'] ?? null,
                'source_snapshot' => $this->sourceSnapshot($collection, $data),
            ]);

            $collection->status = TreasuryCollectionStatus::Receipted;
            $collection->save();

            return $receipt->load(['issuedBy', 'treasuryCollection']);
        });
    }

    /**
     * @param  array{receipt_number: string, numbering_authority: string, remarks?: string|null}  $data
     * @return array<string, mixed>
     */
    private function sourceSnapshot(TreasuryCollection $collection, array $data): array
    {
        return [
            'treasury_collection_id' => $collection->id,
            'collection_status_before' => $collection->status->value,
            'collection_channel' => $collection->channel->value,
            'collection_method' => $collection->method->value,
            'collection_amount_cents' => $collection->amount_cents,
            'payment_schedule_id' => $collection->payment_schedule_id,
            'permit_application' => [
                'id' => $collection->permitApplication->id,
                'application_number' => $collection->permitApplication->application_number,
                'business_name' => $collection->permitApplication->business->name,
                'owner_name' => $collection->permitApplication->business->owner->name,
            ],
            'receipt' => [
                'numbering_authority' => $data['numbering_authority'],
                'receipt_number' => $data['receipt_number'],
            ],
            'policy' => [
                'numbering_mode' => 'manual',
                'note' => 'Receipt number was supplied by the issuing user. Automatic receipt numbering authority and duplication policy remain explicit later decisions.',
            ],
        ];
    }
}
