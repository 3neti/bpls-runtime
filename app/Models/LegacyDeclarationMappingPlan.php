<?php

namespace App\Models;

use App\Enums\LegacyMappingPlanStatus;
use Database\Factories\LegacyDeclarationMappingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property string $run_reference
 * @property string $planner_version
 * @property string $dependency_snapshot_hash
 * @property LegacyMappingPlanStatus $status
 * @property int $proposal_count
 * @property int $ready_count
 * @property int $review_count
 * @property int $blocked_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_import_batch_id', 'run_reference', 'planner_version', 'dependency_snapshot_hash', 'status', 'proposal_count', 'ready_count', 'review_count', 'blocked_count', 'started_at', 'completed_at', 'metadata'])]
class LegacyDeclarationMappingPlan extends Model
{
    /** @use HasFactory<LegacyDeclarationMappingPlanFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'planning', 'proposal_count' => 0, 'ready_count' => 0, 'review_count' => 0, 'blocked_count' => 0];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyDeclarationMappingProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(LegacyDeclarationMappingProposal::class);
    }

    /** @return HasMany<LegacyDeclarationMappingExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(LegacyDeclarationMappingExecution::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LegacyMappingPlanStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }
}
