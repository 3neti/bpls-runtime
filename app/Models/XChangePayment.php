<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $payment_schedule_id
 * @property int $assessment_id
 * @property int|null $treasury_collection_id
 * @property string $external_reference
 * @property string $issue_idempotency_key
 * @property string $terms_hash
 * @property int $amount_cents
 * @property string $currency
 * @property string $binding_secret
 * @property string $status
 * @property string|null $pay_code
 * @property string|null $voucher_id
 * @property string|null $consumer_status
 * @property string|null $provider_status
 * @property int $collected_total_cents
 * @property int|null $target_amount_cents
 * @property bool $is_fully_collected
 * @property Carbon|null $confirmed_at
 * @property string|null $last_error_code
 * @property-read PaymentSchedule $paymentSchedule
 * @property-read Assessment $assessment
 * @property-read TreasuryCollection|null $treasuryCollection
 * @property-read Collection<int, XChangePaymentAttempt> $attempts
 */
#[Fillable(['payment_schedule_id', 'assessment_id', 'treasury_collection_id', 'external_reference', 'issue_idempotency_key', 'terms_hash', 'amount_cents', 'currency', 'binding_secret', 'status', 'pay_code', 'voucher_id', 'consumer_status', 'provider_status', 'collected_total_cents', 'target_amount_cents', 'is_fully_collected', 'confirmed_at', 'last_error_code'])]
class XChangePayment extends Model
{
    protected $hidden = ['binding_secret'];

    /** @return BelongsTo<PaymentSchedule, $this> */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<TreasuryCollection, $this> */
    public function treasuryCollection(): BelongsTo
    {
        return $this->belongsTo(TreasuryCollection::class);
    }

    /** @return HasMany<XChangePaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(XChangePaymentAttempt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'binding_secret' => 'encrypted',
            'is_fully_collected' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }
}
