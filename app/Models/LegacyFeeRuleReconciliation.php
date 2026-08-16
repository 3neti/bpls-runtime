<?php

namespace App\Models;

use App\Enums\LegacyFeeRuleReconciliationStatus;
use Database\Factories\LegacyFeeRuleReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property string $source_dataset
 * @property string $source_legacy_id
 * @property int|null $fee_rule_id
 * @property LegacyFeeRuleReconciliationStatus $status
 * @property string|null $decision_authority
 * @property string|null $evidence_reference
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_source_id', 'source_dataset', 'source_legacy_id', 'fee_rule_id', 'status', 'decision_authority', 'evidence_reference', 'decided_at', 'metadata'])]
class LegacyFeeRuleReconciliation extends Model
{
    /** @use HasFactory<LegacyFeeRuleReconciliationFactory> */
    use HasFactory;

    protected $attributes = ['source_dataset' => 'fees', 'status' => 'pending'];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return BelongsTo<FeeRule, $this> */
    public function feeRule(): BelongsTo
    {
        return $this->belongsTo(FeeRule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyFeeRuleReconciliationStatus::class, 'decided_at' => 'datetime', 'metadata' => 'array'];
    }
}
