<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $fee_rule_id
 * @property int $version
 * @property string $status
 * @property string $currency
 * @property int|null $previous_amount_minor
 * @property int|null $proposed_amount_minor
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string $reason
 * @property string $authority
 * @property int|null $proposed_by_id
 * @property Carbon $proposed_at
 * @property Carbon|null $activated_at
 * @property array<string, mixed> $snapshot
 */
#[Fillable(['fee_rule_id', 'version', 'status', 'currency', 'previous_amount_minor', 'proposed_amount_minor', 'effective_from', 'effective_until', 'reason', 'authority', 'proposed_by_id', 'proposed_at', 'activated_at', 'snapshot'])]
class FeeRuleRevision extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Fee revisions are append-only; record a new revision instead.'));
        static::deleting(fn (): never => throw new LogicException('Fee revisions are append-only audit evidence and cannot be deleted.'));
    }

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_id');
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'proposed_at' => 'datetime',
            'activated_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }
}
