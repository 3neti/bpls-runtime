<?php

namespace App\Models;

use Database\Factories\TreasuryLineItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $treasury_line_of_business_assignment_id
 * @property int $fee_rule_id
 * @property int $determined_by_id
 * @property string $code
 * @property string $name
 * @property int $default_amount_cents
 * @property int $determined_amount_cents
 * @property int $variance_cents
 * @property string $currency
 * @property array<string, mixed> $source_snapshot
 * @property Carbon $determined_at
 * @property Carbon|null $removed_at
 * @property-read TreasuryLineOfBusinessAssignment $assignment
 * @property-read FeeRule $feeRule
 * @property-read User $determinedBy
 */
#[Fillable(['treasury_line_of_business_assignment_id', 'fee_rule_id', 'determined_by_id', 'code', 'name', 'default_amount_cents', 'determined_amount_cents', 'variance_cents', 'currency', 'source_snapshot', 'determined_at', 'removed_at'])]
class TreasuryLineItem extends Model
{
    /** @use HasFactory<TreasuryLineItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if (array_diff(array_keys($item->getDirty()), ['removed_at', 'updated_at']) !== []) {
                throw new LogicException('Treasury financial determination evidence is immutable.');
            }
        });
    }

    /** @return BelongsTo<TreasuryLineOfBusinessAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TreasuryLineOfBusinessAssignment::class, 'treasury_line_of_business_assignment_id');
    }

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by_id');
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'determined_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
