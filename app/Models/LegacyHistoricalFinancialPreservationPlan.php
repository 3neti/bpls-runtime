<?php

namespace App\Models;

use App\Enums\LegacyMappingPlanStatus;
use Database\Factories\LegacyHistoricalFinancialPreservationPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property int $legacy_financial_mapping_plan_id
 * @property string $run_reference
 * @property string $planner_version
 * @property string $dependency_snapshot_hash
 * @property LegacyMappingPlanStatus $status
 * @property int $proposal_count
 * @property int $ready_count
 * @property int $blocked_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_import_batch_id', 'legacy_financial_mapping_plan_id', 'run_reference', 'planner_version', 'dependency_snapshot_hash', 'status', 'proposal_count', 'ready_count', 'blocked_count', 'started_at', 'completed_at', 'metadata'])]
class LegacyHistoricalFinancialPreservationPlan extends Model
{
    /** @use HasFactory<LegacyHistoricalFinancialPreservationPlanFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'planning', 'proposal_count' => 0, 'ready_count' => 0, 'blocked_count' => 0];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return BelongsTo<LegacyFinancialMappingPlan, $this> */
    public function financialMappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyFinancialMappingPlan::class, 'legacy_financial_mapping_plan_id');
    }

    /** @return HasMany<LegacyHistoricalFinancialPreservationProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(LegacyHistoricalFinancialPreservationProposal::class);
    }

    /** @return HasMany<LegacyHistoricalFinancialPreservationExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(LegacyHistoricalFinancialPreservationExecution::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingPlanStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }
}
