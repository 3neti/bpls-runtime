<?php

namespace App\Models;

use App\Enums\RevenueCodeProvisionClauseType;
use App\Enums\RevenueCodeProvisionStatus;
use Database\Factories\RevenueCodeProvisionClauseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $revenue_code_provision_id
 * @property int $sequence
 * @property string $code
 * @property RevenueCodeProvisionClauseType $clause_type
 * @property string $source_text
 * @property string $candidate_interpretation
 * @property int|null $amount_cents
 * @property string|null $rate_basis_points
 * @property bool $is_ceiling
 * @property RevenueCodeProvisionStatus $reconciliation_status
 * @property string $execution_blocker
 * @property array<string, mixed>|null $metadata
 * @property-read RevenueCodeProvision $provision
 */
#[Fillable(['revenue_code_provision_id', 'sequence', 'code', 'clause_type', 'source_text', 'candidate_interpretation', 'amount_cents', 'rate_basis_points', 'is_ceiling', 'reconciliation_status', 'execution_blocker', 'metadata'])]
class RevenueCodeProvisionClause extends Model
{
    /** @use HasFactory<RevenueCodeProvisionClauseFactory> */
    use HasFactory;

    protected $attributes = [
        'is_ceiling' => false,
    ];

    /** @return BelongsTo<RevenueCodeProvision, $this> */
    public function provision(): BelongsTo
    {
        return $this->belongsTo(RevenueCodeProvision::class, 'revenue_code_provision_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'clause_type' => RevenueCodeProvisionClauseType::class,
            'rate_basis_points' => 'decimal:4',
            'is_ceiling' => 'boolean',
            'reconciliation_status' => RevenueCodeProvisionStatus::class,
            'metadata' => 'array',
        ];
    }
}
