<?php

namespace App\Models;

use App\Enums\LegacyClearanceTypeReconciliationStatus;
use Database\Factories\LegacyClearanceTypeReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property string $source_dataset
 * @property string $source_legacy_id
 * @property string|null $target_code
 * @property string|null $target_label
 * @property LegacyClearanceTypeReconciliationStatus $status
 * @property string|null $decision_authority
 * @property string|null $evidence_reference
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_source_id', 'source_dataset', 'source_legacy_id', 'target_code', 'target_label', 'status', 'decision_authority', 'evidence_reference', 'decided_at', 'metadata'])]
class LegacyClearanceTypeReconciliation extends Model
{
    /** @use HasFactory<LegacyClearanceTypeReconciliationFactory> */
    use HasFactory;

    protected $attributes = ['source_dataset' => 'clearance_types', 'status' => 'pending'];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyClearanceTypeReconciliationStatus::class, 'decided_at' => 'datetime', 'metadata' => 'array'];
    }
}
