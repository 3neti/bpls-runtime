<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $x_change_payment_id
 * @property string $idempotency_key
 * @property string|null $reference
 * @property string $status
 * @property string|null $provider
 * @property int $amount_cents
 * @property Carbon|null $expires_at
 * @property-read XChangePayment $xChangePayment
 */
#[Fillable(['x_change_payment_id', 'idempotency_key', 'reference', 'status', 'provider', 'amount_cents', 'expires_at'])]
class XChangePaymentAttempt extends Model
{
    /** @return BelongsTo<XChangePayment, $this> */
    public function xChangePayment(): BelongsTo
    {
        return $this->belongsTo(XChangePayment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
