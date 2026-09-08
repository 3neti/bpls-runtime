<?php

namespace App\Models;

use App\Enums\FeeRuleCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property FeeRuleCategory $fee_rule_category
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'fee_rule_category', 'is_active'])]
class FeeCategory extends Model
{
    /** @return HasMany<FeeRule, $this> */
    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fee_rule_category' => FeeRuleCategory::class, 'is_active' => 'boolean'];
    }
}
