<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_rule_id', 'office_code', 'office_label', 'source', 'metadata'])]
class FeeRuleOfficeAssignment extends Model
{
    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
