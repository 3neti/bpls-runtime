<?php

namespace App\Models;

use Database\Factories\LegacyFinancialSnapshotMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $legacy_financial_mapping_execution_id
 * @property int $legacy_application_id_mapping_id
 * @property int $legacy_source_id
 * @property int $legacy_import_batch_id
 * @property int $legacy_record_id
 * @property int $assessment_id
 * @property int $payment_schedule_id
 * @property string $dataset_key
 * @property string $legacy_id
 * @property string $status
 * @property string $mapping_basis
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_financial_mapping_execution_id', 'legacy_application_id_mapping_id', 'legacy_source_id', 'legacy_import_batch_id', 'legacy_record_id', 'assessment_id', 'payment_schedule_id', 'dataset_key', 'legacy_id', 'status', 'mapping_basis', 'metadata'])]
class LegacyFinancialSnapshotMapping extends Model
{
    /** @use HasFactory<LegacyFinancialSnapshotMappingFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'mapped'];

    /** @return BelongsTo<LegacyFinancialMappingExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(LegacyFinancialMappingExecution::class, 'legacy_financial_mapping_execution_id');
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

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<PaymentSchedule, $this> */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
