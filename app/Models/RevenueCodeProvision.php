<?php

namespace App\Models;

use App\Enums\RevenueCodeProvisionStatus;
use App\Enums\RevenueCodeProvisionType;
use Database\Factories\RevenueCodeProvisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $fee_rule_id
 * @property string $code
 * @property string $source_id
 * @property string $section_reference
 * @property string $title
 * @property RevenueCodeProvisionType $provision_type
 * @property string $evidence_summary
 * @property RevenueCodeProvisionStatus $reconciliation_status
 * @property string|null $reconciliation_notes
 * @property Carbon $effective_from
 * @property array<string, mixed>|null $metadata
 * @property-read FeeRule|null $feeRule
 * @property-read Collection<int, RevenueCodeProvisionRow> $rows
 * @property-read Collection<int, RevenueCodeProvisionClause> $clauses
 */
#[Fillable(['fee_rule_id', 'code', 'source_id', 'section_reference', 'title', 'provision_type', 'evidence_summary', 'reconciliation_status', 'reconciliation_notes', 'effective_from', 'metadata'])]
class RevenueCodeProvision extends Model
{
    /** @use HasFactory<RevenueCodeProvisionFactory> */
    use HasFactory;

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return HasMany<RevenueCodeProvisionRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(RevenueCodeProvisionRow::class)->orderBy('sequence');
    }

    /** @return HasMany<RevenueCodeProvisionClause, $this> */
    public function clauses(): HasMany
    {
        return $this->hasMany(RevenueCodeProvisionClause::class)->orderBy('sequence');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provision_type' => RevenueCodeProvisionType::class,
            'reconciliation_status' => RevenueCodeProvisionStatus::class,
            'effective_from' => 'date',
            'metadata' => 'array',
        ];
    }
}
