<?php

namespace App\Models;

use App\Enums\LegacyImportBatchStatus;
use Database\Factories\LegacyImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property string $run_reference
 * @property string $manifest_schema_version
 * @property string $manifest_checksum
 * @property LegacyImportBatchStatus $status
 * @property int $source_record_count
 * @property int $staged_record_count
 * @property int $exception_count
 * @property int $mapping_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 * @property-read LegacySource $source
 */
#[Fillable(['legacy_source_id', 'run_reference', 'manifest_schema_version', 'manifest_checksum', 'status', 'source_record_count', 'staged_record_count', 'exception_count', 'mapping_count', 'started_at', 'completed_at', 'metadata'])]
class LegacyImportBatch extends Model
{
    /** @use HasFactory<LegacyImportBatchFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'staging',
        'source_record_count' => 0,
        'staged_record_count' => 0,
        'exception_count' => 0,
        'mapping_count' => 0,
    ];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return HasMany<LegacyRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(LegacyRecord::class);
    }

    /** @return HasMany<LegacyIdMapping, $this> */
    public function idMappings(): HasMany
    {
        return $this->hasMany(LegacyIdMapping::class);
    }

    /** @return HasMany<MigrationValidationResult, $this> */
    public function validationResults(): HasMany
    {
        return $this->hasMany(MigrationValidationResult::class);
    }

    /** @return HasMany<LegacyMigrationException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(LegacyMigrationException::class);
    }

    /** @return HasMany<LegacyMappingPlan, $this> */
    public function mappingPlans(): HasMany
    {
        return $this->hasMany(LegacyMappingPlan::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyApplicationMappingPlan, $this> */
    public function applicationMappingPlans(): HasMany
    {
        return $this->hasMany(LegacyApplicationMappingPlan::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyDeclarationMappingPlan, $this> */
    public function declarationMappingPlans(): HasMany
    {
        return $this->hasMany(LegacyDeclarationMappingPlan::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyFinancialMappingPlan, $this> */
    public function financialMappingPlans(): HasMany
    {
        return $this->hasMany(LegacyFinancialMappingPlan::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyFinancialSnapshotMapping, $this> */
    public function financialSnapshotMappings(): HasMany
    {
        return $this->hasMany(LegacyFinancialSnapshotMapping::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyPermitEvidencePlan, $this> */
    public function permitEvidencePlans(): HasMany
    {
        return $this->hasMany(LegacyPermitEvidencePlan::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyPermitClearanceMapping, $this> */
    public function permitClearanceMappings(): HasMany
    {
        return $this->hasMany(LegacyPermitClearanceMapping::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyDocumentObjectStagingRun, $this> */
    public function documentObjectStagingRuns(): HasMany
    {
        return $this->hasMany(LegacyDocumentObjectStagingRun::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyPermitDocumentMapping, $this> */
    public function permitDocumentMappings(): HasMany
    {
        return $this->hasMany(LegacyPermitDocumentMapping::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyMigrationRehearsal, $this> */
    public function migrationRehearsals(): HasMany
    {
        return $this->hasMany(LegacyMigrationRehearsal::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyMigrationReadinessAssessment, $this> */
    public function readinessAssessments(): HasMany
    {
        return $this->hasMany(LegacyMigrationReadinessAssessment::class, 'legacy_import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyImportBatchStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
