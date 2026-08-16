<?php

namespace App\Models;

use Database\Factories\LegacyApplicationIdMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $legacy_application_mapping_execution_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $permit_application_id
 * @property string $dataset_key
 * @property string $legacy_id
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_application_mapping_execution_id', 'legacy_source_id', 'legacy_import_batch_id', 'permit_application_id', 'dataset_key', 'legacy_id', 'status', 'mapping_basis', 'metadata'])]
class LegacyApplicationIdMapping extends Model
{
    /** @use HasFactory<LegacyApplicationIdMappingFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'mapped'];

    /** @return BelongsTo<LegacyApplicationMappingExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationMappingExecution::class, 'legacy_application_mapping_execution_id');
    }

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

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
