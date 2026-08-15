<?php

namespace App\Models;

use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use Database\Factories\FeeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $line_of_business_id
 * @property string $code
 * @property string $name
 * @property FeeRuleCategory $category
 * @property FeeRuleScope $scope
 * @property FeeRuleCalculationType $calculation_type
 * @property string $basis
 * @property int $amount_cents
 * @property int|null $rate_basis_points
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string|null $legal_basis
 * @property bool $is_active
 * @property string|null $legacy_source_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LineOfBusiness|null $lineOfBusiness
 * @property-read Collection<int, FeeRuleRange> $ranges
 * @property-read Collection<int, FeeRuleReconciliation> $reconciliations
 * @property-read FeeRuleReconciliation|null $currentReconciliation
 */
#[Fillable(['line_of_business_id', 'code', 'name', 'category', 'scope', 'calculation_type', 'basis', 'amount_cents', 'rate_basis_points', 'effective_from', 'effective_until', 'legal_basis', 'is_active', 'legacy_source_id', 'metadata'])]
class FeeRule extends Model
{
    /** @use HasFactory<FeeRuleFactory> */
    use HasFactory;

    protected $attributes = [
        'basis' => 'none',
        'amount_cents' => 0,
        'is_active' => true,
    ];

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /** @return HasMany<FeeRuleRange, $this> */
    public function ranges(): HasMany
    {
        return $this->hasMany(FeeRuleRange::class);
    }

    /** @return HasMany<FeeRuleReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(FeeRuleReconciliation::class);
    }

    /** @return HasOne<FeeRuleReconciliation, $this> */
    public function currentReconciliation(): HasOne
    {
        return $this->hasOne(FeeRuleReconciliation::class)->ofMany('version', 'max');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeRuleCategory::class,
            'scope' => FeeRuleScope::class,
            'calculation_type' => FeeRuleCalculationType::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
