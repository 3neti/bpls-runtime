<?php

namespace App\Models;

use Database\Factories\LegacyPermitDocumentMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $legacy_permit_evidence_execution_id
 * @property int $legacy_application_id_mapping_id
 * @property int $legacy_document_object_reconciliation_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $legacy_record_id
 * @property int $permit_application_document_id
 * @property string $item_key
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_permit_evidence_execution_id', 'legacy_application_id_mapping_id', 'legacy_document_object_reconciliation_id', 'legacy_source_id', 'legacy_import_batch_id', 'legacy_record_id', 'permit_application_document_id', 'item_key', 'status', 'mapping_basis', 'metadata'])]
class LegacyPermitDocumentMapping extends Model
{
    /** @use HasFactory<LegacyPermitDocumentMappingFactory> */
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

    /** @return BelongsTo<LegacyDocumentObjectReconciliation, $this> */
    public function documentReconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyDocumentObjectReconciliation::class, 'legacy_document_object_reconciliation_id');
    }

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<PermitApplicationDocument, $this> */
    public function permitApplicationDocument(): BelongsTo
    {
        return $this->belongsTo(PermitApplicationDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
