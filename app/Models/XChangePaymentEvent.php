<?php

namespace App\Models;

use Database\Factories\XChangePaymentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $payload_hash
 * @property string $external_reference
 * @property string $pay_code
 * @property string $currency
 * @property int $amount_minor
 * @property string $state
 * @property Carbon $created_at
 */
#[Fillable(['event_id', 'payload_hash', 'partner_reference', 'external_reference', 'pay_code', 'provider_collection_id', 'amount_minor', 'currency', 'occurred_at', 'state', 'failure_code', 'processed_at'])]
class XChangePaymentEvent extends Model
{
    /** @use HasFactory<XChangePaymentEventFactory> */
    use HasFactory;

    protected $attributes = ['state' => 'accepted'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
