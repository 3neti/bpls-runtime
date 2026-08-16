<?php

namespace App\Models;

use App\Enums\LegacyMigrationRehearsalStatus;
use Database\Factories\LegacyMigrationRehearsalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property int $legacy_mapping_execution_id
 * @property int $legacy_application_mapping_execution_id
 * @property int|null $legacy_declaration_mapping_execution_id
 * @property int|null $legacy_financial_mapping_execution_id
 * @property int|null $legacy_permit_evidence_execution_id
 * @property int|null $legacy_migration_readiness_assessment_id
 * @property string $run_reference
 * @property string $verifier_version
 * @property string $selection_hash
 * @property string $dependency_snapshot_hash
 * @property LegacyMigrationRehearsalStatus $status
 * @property int $check_count
 * @property int $passed_count
 * @property int $blocked_count
 * @property list<array<string, mixed>>|null $checks
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $rolled_back_at
 * @property array<string, mixed>|null $metadata
 * @property-read LegacyImportBatch $importBatch
 * @property-read LegacyMappingExecution $registryExecution
 * @property-read LegacyApplicationMappingExecution $applicationExecution
 * @property-read LegacyDeclarationMappingExecution|null $declarationExecution
 * @property-read LegacyFinancialMappingExecution|null $financialExecution
 * @property-read LegacyPermitEvidenceExecution|null $permitEvidenceExecution
 * @property-read LegacyMigrationReadinessAssessment|null $readinessAssessment
 */
#[Fillable(['legacy_import_batch_id', 'legacy_mapping_execution_id', 'legacy_application_mapping_execution_id', 'legacy_declaration_mapping_execution_id', 'legacy_financial_mapping_execution_id', 'legacy_permit_evidence_execution_id', 'legacy_migration_readiness_assessment_id', 'run_reference', 'verifier_version', 'selection_hash', 'dependency_snapshot_hash', 'status', 'check_count', 'passed_count', 'blocked_count', 'checks', 'started_at', 'completed_at', 'rolled_back_at', 'metadata'])]
class LegacyMigrationRehearsal extends Model
{
    /** @use HasFactory<LegacyMigrationRehearsalFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'verifying',
        'check_count' => 0,
        'passed_count' => 0,
        'blocked_count' => 0,
    ];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return BelongsTo<LegacyMappingExecution, $this> */
    public function registryExecution(): BelongsTo
    {
        return $this->belongsTo(LegacyMappingExecution::class, 'legacy_mapping_execution_id');
    }

    /** @return BelongsTo<LegacyApplicationMappingExecution, $this> */
    public function applicationExecution(): BelongsTo
    {
        return $this->belongsTo(LegacyApplicationMappingExecution::class, 'legacy_application_mapping_execution_id');
    }

    /** @return BelongsTo<LegacyDeclarationMappingExecution, $this> */
    public function declarationExecution(): BelongsTo
    {
        return $this->belongsTo(LegacyDeclarationMappingExecution::class, 'legacy_declaration_mapping_execution_id');
    }

    /** @return BelongsTo<LegacyFinancialMappingExecution, $this> */
    public function financialExecution(): BelongsTo
    {
        return $this->belongsTo(LegacyFinancialMappingExecution::class, 'legacy_financial_mapping_execution_id');
    }

    /** @return BelongsTo<LegacyPermitEvidenceExecution, $this> */
    public function permitEvidenceExecution(): BelongsTo
    {
        return $this->belongsTo(LegacyPermitEvidenceExecution::class, 'legacy_permit_evidence_execution_id');
    }

    /** @return BelongsTo<LegacyMigrationReadinessAssessment, $this> */
    public function readinessAssessment(): BelongsTo
    {
        return $this->belongsTo(LegacyMigrationReadinessAssessment::class, 'legacy_migration_readiness_assessment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyMigrationRehearsalStatus::class,
            'checks' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
