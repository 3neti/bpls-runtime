<?php

namespace App\Models;

use App\Enums\TreasuryCollectionChannel;
use App\Enums\TreasuryCollectionMethod;
use App\Enums\TreasuryCollectionStatus;
use Database\Factories\TreasuryCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_schedule_id
 * @property int $permit_application_id
 * @property int $assessment_id
 * @property int|null $received_by_id
 * @property TreasuryCollectionStatus $status
 * @property TreasuryCollectionChannel $channel
 * @property TreasuryCollectionMethod $method
 * @property int $amount_cents
 * @property string|null $payer_name
 * @property string|null $reference_number
 * @property string|null $remarks
 * @property Carbon $received_at
 * @property array<string, mixed> $source_snapshot
 * @property string|null $legacy_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['payment_schedule_id', 'permit_application_id', 'assessment_id', 'received_by_id', 'status', 'channel', 'method', 'amount_cents', 'payer_name', 'reference_number', 'remarks', 'received_at', 'source_snapshot', 'legacy_source_id'])]
class TreasuryCollection extends Model
{
    /** @use HasFactory<TreasuryCollectionFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending_receipt',
        'channel' => 'over_the_counter',
    ];

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TreasuryCollectionStatus::class,
            'channel' => TreasuryCollectionChannel::class,
            'method' => TreasuryCollectionMethod::class,
            'received_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
