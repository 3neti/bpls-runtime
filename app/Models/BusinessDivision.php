<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['code', 'name', 'is_active', 'metadata'])]
class BusinessDivision extends Model
{
    /** @return BelongsToMany<LineOfBusiness, $this> */
    public function linesOfBusiness(): BelongsToMany
    {
        return $this->belongsToMany(LineOfBusiness::class, 'business_division_line_of_business')->withTimestamps();
    }

    /** @return HasMany<FeeRule, $this> */
    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }
}
