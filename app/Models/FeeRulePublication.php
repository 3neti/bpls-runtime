<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fee_rule_id
 * @property int $fee_rule_revision_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string $snapshot_sha256
 * @property array<string, mixed> $snapshot
 */
class FeeRulePublication extends Model
{
    protected $fillable = ['fee_rule_id', 'fee_rule_revision_id', 'published_by_id', 'effective_from', 'effective_until', 'snapshot', 'snapshot_sha256', 'published_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'effective_from' => 'date', 'effective_until' => 'date', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Published prices are immutable. Publish a successor revision.'));
        static::deleting(fn (): never => throw new \LogicException('Published price evidence must be retained.'));
    }
}
