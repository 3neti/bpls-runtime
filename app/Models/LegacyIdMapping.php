<?php

namespace App\Models;

use Database\Factories\LegacyIdMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property string $dataset_key
 * @property string $entity_type
 * @property string $legacy_id
 * @property string $target_type
 * @property int $target_id
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_source_id', 'legacy_import_batch_id', 'dataset_key', 'entity_type', 'legacy_id', 'target_type', 'target_id', 'status', 'mapping_basis', 'metadata'])]
class LegacyIdMapping extends Model
{
    /** @use HasFactory<LegacyIdMappingFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'mapped'];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
