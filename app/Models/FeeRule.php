<?php

namespace App\Models;

use App\Enums\FeeDeterminationChannel;
use App\Enums\FeeRuleCalculationType;
use App\Enums\FeeRuleCategory;
use App\Enums\FeeRuleScope;
use Database\Factories\FeeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $fee_catalog_version_id
 * @property int|null $line_of_business_id
 * @property int|null $business_division_id
 * @property string $code
 * @property string $name
 * @property FeeRuleCategory $category
 * @property FeeRuleScope $scope
 * @property FeeDeterminationChannel $determination_channel
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
 * @property-read FeeCatalogVersion|null $catalogVersion
 * @property-read BusinessDivision|null $businessDivision
 * @property-read FeeCategory|null $feeCategory
 * @property-read RevenueAccount|null $revenueAccount
 * @property-read Collection<int, FeeRuleRange> $ranges
 * @property-read Collection<int, FeeRuleReconciliation> $reconciliations
 * @property-read FeeRuleReconciliation|null $currentReconciliation
 */
#[Fillable(['fee_catalog_version_id', 'line_of_business_id', 'business_division_id', 'code', 'name', 'category', 'fee_category_id', 'revenue_account_id', 'scope', 'determination_channel', 'calculation_type', 'basis', 'amount_cents', 'rate_basis_points', 'effective_from', 'effective_until', 'legal_basis', 'is_active', 'legacy_source_id', 'metadata'])]
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

    /** @return BelongsTo<FeeCatalogVersion, $this> */
    public function catalogVersion(): BelongsTo
    {
        return $this->belongsTo(FeeCatalogVersion::class, 'fee_catalog_version_id');
    }

    /** @return BelongsTo<BusinessDivision, $this> */
    public function businessDivision(): BelongsTo
    {
        return $this->belongsTo(BusinessDivision::class);
    }

    /** @return BelongsTo<FeeCategory, $this> */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    /** @return BelongsTo<RevenueAccount, $this> */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(RevenueAccount::class);
    }

    /** @return BelongsToMany<LineOfBusiness, $this> */
    public function lineOfBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(LineOfBusiness::class, 'fee_rule_line_of_business')
            ->withPivot(['source', 'metadata'])
            ->withTimestamps();
    }

    /** @return HasMany<FeeRuleLineOfBusiness, $this> */
    public function lineOfBusinessAssignments(): HasMany
    {
        return $this->hasMany(FeeRuleLineOfBusiness::class);
    }

    /** @return HasMany<FeeRuleOfficeAssignment, $this> */
    public function officeAssignments(): HasMany
    {
        return $this->hasMany(FeeRuleOfficeAssignment::class);
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

    /** @return HasMany<FeeRuleRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(FeeRuleRevision::class)->orderBy('version');
    }

    /** @return HasMany<FeeRuleAuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(FeeRuleAuditEvent::class)->orderBy('occurred_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeRuleCategory::class,
            'scope' => FeeRuleScope::class,
            'determination_channel' => FeeDeterminationChannel::class,
            'calculation_type' => FeeRuleCalculationType::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
