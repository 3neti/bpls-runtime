<?php

namespace App\Models;

use Database\Factories\BploRoutingWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $bplo_routing_determination_id
 * @property string $office_code
 * @property string $office_label
 * @property string $situational_reason
 * @property string $required_work
 * @property int|null $permit_application_line_id
 * @property int|null $line_of_business_id
 * @property array<string, mixed> $context_snapshot
 * @property-read BploRoutingDetermination $determination
 * @property-read Collection<int, PaperlessPaymentOrder> $paymentOrders
 */
#[Fillable(['bplo_routing_determination_id', 'office_code', 'office_label', 'situational_reason', 'required_work', 'permit_application_line_id', 'line_of_business_id', 'context_snapshot'])]
class BploRoutingWork extends Model
{
    /** @use HasFactory<BploRoutingWorkFactory> */
    use HasFactory;

    /** @return BelongsTo<BploRoutingDetermination, $this> */
    public function determination(): BelongsTo
    {
        return $this->belongsTo(BploRoutingDetermination::class, 'bplo_routing_determination_id');
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

    /** @return HasMany<PaperlessPaymentOrder, $this> */
    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaperlessPaymentOrder::class)->orderBy('sequence');
    }

    protected function casts(): array
    {
        return ['context_snapshot' => 'array'];
    }
}
