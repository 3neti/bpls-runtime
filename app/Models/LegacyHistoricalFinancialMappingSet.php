<?php

namespace App\Models;

use Database\Factories\LegacyHistoricalFinancialMappingSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_source_id
 * @property int $financial_import_batch_id
 * @property int $registry_import_batch_id
 * @property int $legacy_financial_mapping_plan_id
 * @property int $legacy_mapping_plan_id
 * @property int|null $accepted_registry_plan_id
 * @property int|null $registry_execution_id
 * @property int|null $declaration_plan_id
 * @property int|null $application_plan_id
 * @property int|null $application_execution_id
 * @property string $run_reference
 * @property string $cohort_sha256
 * @property string $proposal_package_sha256
 * @property string|null $accepted_mapping_set_sha256
 * @property string $status
 * @property int $cohort_size
 * @property string $decision_authority
 * @property string $evidence_reference
 * @property Carbon|null $accepted_at
 * @property array<string, mixed>|null $manifest
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'legacy_source_id',
    'financial_import_batch_id',
    'registry_import_batch_id',
    'legacy_financial_mapping_plan_id',
    'legacy_mapping_plan_id',
    'accepted_registry_plan_id',
    'registry_execution_id',
    'declaration_plan_id',
    'application_plan_id',
    'application_execution_id',
    'run_reference',
    'cohort_sha256',
    'proposal_package_sha256',
    'accepted_mapping_set_sha256',
    'status',
    'cohort_size',
    'decision_authority',
    'evidence_reference',
    'accepted_at',
    'manifest',
    'metadata',
])]
class LegacyHistoricalFinancialMappingSet extends Model
{
    /** @use HasFactory<LegacyHistoricalFinancialMappingSetFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'accepting'];

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return BelongsTo<LegacyFinancialMappingPlan, $this> */
    public function financialMappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyFinancialMappingPlan::class, 'legacy_financial_mapping_plan_id');
    }

    /** @return BelongsTo<LegacyMappingPlan, $this> */
    public function registryMappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyMappingPlan::class, 'legacy_mapping_plan_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'manifest' => 'array',
            'metadata' => 'array',
        ];
    }
}
