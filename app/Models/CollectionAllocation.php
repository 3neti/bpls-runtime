<?php

namespace App\Models;

use Database\Factories\CollectionAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $treasury_collection_id
 * @property int $payment_schedule_line_id
 * @property int|null $receipt_id
 * @property string|null $receipt_group_key
 * @property string|null $receipt_group_label
 * @property int $amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PaymentScheduleLine $paymentScheduleLine
 * @property-read TreasuryCollection $treasuryCollection
 * @property-read Receipt|null $receipt
 */
#[Fillable(['treasury_collection_id', 'payment_schedule_line_id', 'receipt_id', 'receipt_group_key', 'receipt_group_label', 'amount_cents', 'source_snapshot'])]
class CollectionAllocation extends Model
{
    /** @use HasFactory<CollectionAllocationFactory> */
    use HasFactory;

    /** @return BelongsTo<TreasuryCollection, $this> */
    public function treasuryCollection(): BelongsTo
    {
        return $this->belongsTo(TreasuryCollection::class);
    }

    /** @return BelongsTo<PaymentScheduleLine, $this> */
    public function paymentScheduleLine(): BelongsTo
    {
        return $this->belongsTo(PaymentScheduleLine::class);
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
        ];
    }
}
