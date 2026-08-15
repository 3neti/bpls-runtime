<?php

namespace App\Models;

use App\Enums\FeeRuleExecutionStatus;
use Database\Factories\FeeRuleReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fee_rule_id
 * @property int $version
 * @property string $legal_authority
 * @property string $evidence_reference
 * @property string $original_text
 * @property string|null $normalized_interpretation
 * @property string|null $decision_authority
 * @property string|null $decision_reference
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property FeeRuleExecutionStatus $execution_status
 * @property string $execution_reason
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fee_rule_id', 'version', 'legal_authority', 'evidence_reference', 'original_text', 'normalized_interpretation', 'decision_authority', 'decision_reference', 'effective_from', 'effective_until', 'execution_status', 'execution_reason', 'decided_at'])]
class FeeRuleReconciliation extends Model
{
    /** @use HasFactory<FeeRuleReconciliationFactory> */
    use HasFactory;

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'execution_status' => FeeRuleExecutionStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
