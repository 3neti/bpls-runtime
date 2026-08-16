<?php

namespace App\Models;

use App\Enums\LegacyMappingExecutionStatus;
use Database\Factories\LegacyDeclarationMappingExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_declaration_mapping_plan_id
 * @property string $run_reference
 * @property string $selection_hash
 * @property LegacyMappingExecutionStatus $status
 * @property int $selected_count
 * @property int $created_count
 * @property int $reused_count
 * @property int $mapping_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $rolled_back_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_declaration_mapping_plan_id', 'run_reference', 'selection_hash', 'status', 'selected_count', 'created_count', 'reused_count', 'mapping_count', 'started_at', 'completed_at', 'rolled_back_at', 'metadata'])]
class LegacyDeclarationMappingExecution extends Model
{
    /** @use HasFactory<LegacyDeclarationMappingExecutionFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'executing',
        'selected_count' => 0,
        'created_count' => 0,
        'reused_count' => 0,
        'mapping_count' => 0,
    ];

    /** @return BelongsTo<LegacyDeclarationMappingPlan, $this> */
    public function mappingPlan(): BelongsTo
    {
        return $this->belongsTo(LegacyDeclarationMappingPlan::class, 'legacy_declaration_mapping_plan_id');
    }

    /** @return HasMany<LegacyDeclarationLineMapping, $this> */
    public function mappings(): HasMany
    {
        return $this->hasMany(LegacyDeclarationLineMapping::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyMappingExecutionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
