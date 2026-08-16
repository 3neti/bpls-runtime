<?php

namespace App\Models;

use App\Enums\LegacyDocumentObjectReconciliationStatus;
use Database\Factories\LegacyDocumentObjectReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_document_object_staging_run_id
 * @property int $legacy_record_id
 * @property int $legacy_application_id_mapping_id
 * @property string $item_key
 * @property string $storage_reference_hash
 * @property string $document_type_hash
 * @property string $original_name_hash
 * @property string $object_checksum
 * @property int $size_bytes
 * @property string $mime_type
 * @property string $staged_disk
 * @property string $staged_path
 * @property LegacyDocumentObjectReconciliationStatus $status
 * @property string $decision_authority
 * @property string $evidence_reference
 * @property Carbon $decided_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_document_object_staging_run_id', 'legacy_record_id', 'legacy_application_id_mapping_id', 'item_key', 'storage_reference_hash', 'document_type_hash', 'original_name_hash', 'object_checksum', 'size_bytes', 'mime_type', 'staged_disk', 'staged_path', 'status', 'decision_authority', 'evidence_reference', 'decided_at', 'metadata'])]
class LegacyDocumentObjectReconciliation extends Model
{
    /** @use HasFactory<LegacyDocumentObjectReconciliationFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'accepted'];

    /** @return BelongsTo<LegacyDocumentObjectStagingRun, $this> */
    public function stagingRun(): BelongsTo
    {
        return $this->belongsTo(LegacyDocumentObjectStagingRun::class, 'legacy_document_object_staging_run_id');
    }

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<LegacyApplicationIdMapping, $this> */
    public function applicationMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationIdMapping::class, 'legacy_application_id_mapping_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyDocumentObjectReconciliationStatus::class,
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
