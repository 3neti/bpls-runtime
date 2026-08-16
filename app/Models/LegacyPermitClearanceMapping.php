<?php

namespace App\Models;

use Database\Factories\LegacyPermitClearanceMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $legacy_permit_evidence_execution_id
 * @property int $legacy_application_id_mapping_id
 * @property int $legacy_clearance_type_reconciliation_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $legacy_record_id
 * @property int $permit_clearance_id
 * @property string $dataset_key
 * @property string $legacy_id
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_permit_evidence_execution_id', 'legacy_application_id_mapping_id', 'legacy_clearance_type_reconciliation_id', 'legacy_source_id', 'legacy_import_batch_id', 'legacy_record_id', 'permit_clearance_id', 'dataset_key', 'legacy_id', 'status', 'mapping_basis', 'metadata'])]
class LegacyPermitClearanceMapping extends Model
{
    /** @use HasFactory<LegacyPermitClearanceMappingFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'mapped'];

    /** @return BelongsTo<LegacyPermitEvidenceExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(LegacyPermitEvidenceExecution::class, 'legacy_permit_evidence_execution_id');
    }

    /** @return BelongsTo<LegacyApplicationIdMapping, $this> */
    public function applicationMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationIdMapping::class, 'legacy_application_id_mapping_id');
    }

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return BelongsTo<LegacyClearanceTypeReconciliation, $this> */
    public function clearanceReconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyClearanceTypeReconciliation::class, 'legacy_clearance_type_reconciliation_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<PermitClearance, $this> */
    public function permitClearance(): BelongsTo
    {
        return $this->belongsTo(PermitClearance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
