<?php

namespace App\Models;

use Database\Factories\LegacyHistoricalFinancialPreservedBundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $legacy_historical_financial_preservation_execution_id
 * @property int $legacy_historical_financial_preservation_proposal_id
 * @property int $legacy_application_id_mapping_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $legacy_record_id
 * @property int $permit_application_id
 * @property string $source_projection_hash
 * @property string $bundle_snapshot_hash
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed> $snapshot
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_historical_financial_preservation_execution_id', 'legacy_historical_financial_preservation_proposal_id', 'legacy_application_id_mapping_id', 'legacy_source_id', 'legacy_import_batch_id', 'legacy_record_id', 'permit_application_id', 'source_projection_hash', 'bundle_snapshot_hash', 'status', 'mapping_basis', 'snapshot', 'metadata'])]
class LegacyHistoricalFinancialPreservedBundle extends Model
{
    /** @use HasFactory<LegacyHistoricalFinancialPreservedBundleFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'preserved'];

    protected static function booted(): void
    {
        static::updating(function (self $bundle): void {
            if ($bundle->isDirty([
                'legacy_historical_financial_preservation_execution_id',
                'legacy_historical_financial_preservation_proposal_id',
                'legacy_application_id_mapping_id',
                'legacy_source_id',
                'legacy_import_batch_id',
                'legacy_record_id',
                'permit_application_id',
                'source_projection_hash',
                'bundle_snapshot_hash',
                'mapping_basis',
                'snapshot',
            ])) {
                throw new RuntimeException('Historical financial preservation bundle evidence is immutable.');
            }
        });
    }

    /** @return BelongsTo<LegacyHistoricalFinancialPreservationExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(LegacyHistoricalFinancialPreservationExecution::class, 'legacy_historical_financial_preservation_execution_id');
    }

    /** @return BelongsTo<LegacyHistoricalFinancialPreservationProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(LegacyHistoricalFinancialPreservationProposal::class, 'legacy_historical_financial_preservation_proposal_id');
    }

    /** @return BelongsTo<LegacyApplicationIdMapping, $this> */
    public function applicationMapping(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationIdMapping::class, 'legacy_application_id_mapping_id');
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

    /** @return BelongsTo<LegacyRecord, $this> */
    public function legacyRecord(): BelongsTo
    {
        return $this->belongsTo(LegacyRecord::class);
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'metadata' => 'array'];
    }
}
