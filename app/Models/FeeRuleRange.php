<?php

namespace App\Models;

use Database\Factories\FeeRuleRangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fee_rule_id
 * @property int $min_basis_cents
 * @property int|null $max_basis_cents
 * @property int $amount_cents
 * @property int|null $rate_basis_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fee_rule_id', 'min_basis_cents', 'max_basis_cents', 'amount_cents', 'rate_basis_points'])]
class FeeRuleRange extends Model
{
    /** @use HasFactory<FeeRuleRangeFactory> */
    use HasFactory;

    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }
}
