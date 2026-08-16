<?php

namespace App\Models;

use App\Enums\LegacyMappingProposalStatus;
use Database\Factories\LegacyFinancialMappingProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_financial_mapping_plan_id
 * @property int $legacy_record_id
 * @property string $source_dataset
 * @property string $kind
 * @property string $item_key
 * @property int|null $legacy_fee_rule_reconciliation_id
 * @property int|null $fee_rule_id
 * @property LegacyMappingProposalStatus $status
 * @property string $projection_hash
 * @property list<string>|null $reasons
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_financial_mapping_plan_id', 'legacy_record_id', 'source_dataset', 'kind', 'item_key', 'legacy_fee_rule_reconciliation_id', 'fee_rule_id', 'status', 'projection_hash', 'reasons', 'metadata'])]
class LegacyFinancialMappingProposal extends Model
{
    /** @use HasFactory<LegacyFinancialMappingProposalFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyFinancialMappingPlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyFinancialMappingPlan::class, 'legacy_financial_mapping_plan_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyFeeRuleReconciliation, $this> */
    public function feeReconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyFeeRuleReconciliation::class, 'legacy_fee_rule_reconciliation_id');
    }

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingProposalStatus::class, 'reasons' => 'array', 'metadata' => 'array'];
    }
}
