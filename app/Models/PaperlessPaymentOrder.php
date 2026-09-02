<?php

namespace App\Models;

use Database\Factories\PaperlessPaymentOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int $bplo_routing_work_id
 * @property int|null $business_permit_evaluation_item_revision_id
 * @property int $issued_by_id
 * @property int $sequence
 * @property string $status
 * @property int $total_amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property Carbon $issued_at
 * @property Carbon|null $superseded_at
 * @property-read BploRoutingWork $routingWork
 * @property-read Collection<int, PaperlessPaymentOrderLine> $lines
 */
#[Fillable(['permit_application_id', 'bplo_routing_work_id', 'business_permit_evaluation_item_revision_id', 'issued_by_id', 'sequence', 'status', 'total_amount_cents', 'source_snapshot', 'issued_at', 'superseded_at'])]
class PaperlessPaymentOrder extends Model
{
    /** @use HasFactory<PaperlessPaymentOrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            $changed = array_keys($order->getDirty());
            $allowedLifecycleFields = ['status', 'superseded_at', 'updated_at'];

            if (array_diff($changed, $allowedLifecycleFields) !== []) {
                throw new LogicException('An issued Paperless Payment Order financial determination is immutable.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('An issued Paperless Payment Order cannot be deleted.'));
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<BploRoutingWork, $this> */
    public function routingWork(): BelongsTo
    {
        return $this->belongsTo(BploRoutingWork::class, 'bplo_routing_work_id');
    }

    /** @return BelongsTo<BusinessPermitEvaluationItemRevision, $this> */
    public function evaluationItemRevision(): BelongsTo
    {
        return $this->belongsTo(BusinessPermitEvaluationItemRevision::class, 'business_permit_evaluation_item_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /** @return HasMany<PaperlessPaymentOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaperlessPaymentOrderLine::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'issued_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
