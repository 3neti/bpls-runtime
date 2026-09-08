<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_rule_id', 'line_of_business_id', 'source', 'metadata'])]
class FeeRuleLineOfBusiness extends Model
{
    protected $table = 'fee_rule_line_of_business';

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
