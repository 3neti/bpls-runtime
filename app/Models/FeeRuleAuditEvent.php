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
 * @property int|null $fee_rule_revision_id
 * @property string $event
 * @property int|null $actor_id
 * @property Carbon $occurred_at
 * @property array<string, mixed> $snapshot
 * @property-read User|null $actor
 */
#[Fillable(['fee_rule_id', 'fee_rule_revision_id', 'event', 'actor_id', 'occurred_at', 'snapshot'])]
class FeeRuleAuditEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Fee audit events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Fee audit events cannot be deleted.'));
    }

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return BelongsTo<FeeRuleRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(FeeRuleRevision::class, 'fee_rule_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'snapshot' => 'array'];
    }
}
