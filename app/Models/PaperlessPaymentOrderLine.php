<?php

namespace App\Models;

use Database\Factories\PaperlessPaymentOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $paperless_payment_order_id
 * @property int|null $permit_application_line_id
 * @property int|null $line_of_business_id
 * @property string $code
 * @property string $name
 * @property int $amount_cents
 * @property array<string, mixed> $source_snapshot
 */
#[Fillable(['paperless_payment_order_id', 'permit_application_line_id', 'line_of_business_id', 'code', 'name', 'amount_cents', 'source_snapshot'])]
class PaperlessPaymentOrderLine extends Model
{
    /** @use HasFactory<PaperlessPaymentOrderLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('An issued Paperless Payment Order line is immutable.'));
        static::deleting(fn (): never => throw new LogicException('An issued Paperless Payment Order line cannot be deleted.'));
    }

    /** @return BelongsTo<PaperlessPaymentOrder, $this> */
    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaperlessPaymentOrder::class, 'paperless_payment_order_id');
    }

    /** @return BelongsTo<PermitApplicationLine, $this> */
    public function permitApplicationLine(): BelongsTo
    {
        return $this->belongsTo(PermitApplicationLine::class);
    }

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'array'];
    }
}
