<?php

namespace App\Models;

use Database\Factories\LegacyDeclarationLineMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $legacy_declaration_mapping_execution_id
 * @property int $legacy_application_id_mapping_id
 * @property int $legacy_line_of_business_reconciliation_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $permit_application_line_id
 * @property string $dataset_key
 * @property string $legacy_id
 * @property int $line_index
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_declaration_mapping_execution_id', 'legacy_application_id_mapping_id', 'legacy_line_of_business_reconciliation_id', 'legacy_source_id', 'legacy_import_batch_id', 'permit_application_line_id', 'dataset_key', 'legacy_id', 'line_index', 'status', 'mapping_basis', 'metadata'])]
class LegacyDeclarationLineMapping extends Model
{
    /** @use HasFactory<LegacyDeclarationLineMappingFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'mapped'];

    /** @return BelongsTo<LegacyDeclarationMappingExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(LegacyDeclarationMappingExecution::class, 'legacy_declaration_mapping_execution_id');
    }

    /** @return BelongsTo<LegacyApplicationIdMapping, $this> */
    public function applicationMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationIdMapping::class, 'legacy_application_id_mapping_id');
    }

    /** @return BelongsTo<LegacyLineOfBusinessReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(LegacyLineOfBusinessReconciliation::class, 'legacy_line_of_business_reconciliation_id');
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

    /** @return BelongsTo<PermitApplicationLine, $this> */
    public function permitApplicationLine(): BelongsTo
    {
        return $this->belongsTo(PermitApplicationLine::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
