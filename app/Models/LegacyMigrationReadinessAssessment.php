<?php

namespace App\Models;

use App\Enums\LegacyMigrationReadinessStatus;
use Database\Factories\LegacyMigrationReadinessAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property string $run_reference
 * @property string $assessor_version
 * @property string $dependency_snapshot_hash
 * @property LegacyMigrationReadinessStatus $status
 * @property bool $rehearsal_ready
 * @property bool $cutover_ready
 * @property int $check_count
 * @property int $passed_count
 * @property int $blocked_count
 * @property list<array<string, mixed>>|null $checks
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_import_batch_id', 'run_reference', 'assessor_version', 'dependency_snapshot_hash', 'status', 'rehearsal_ready', 'cutover_ready', 'check_count', 'passed_count', 'blocked_count', 'checks', 'started_at', 'completed_at', 'metadata'])]
class LegacyMigrationReadinessAssessment extends Model
{
    /** @use HasFactory<LegacyMigrationReadinessAssessmentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'assessing',
        'rehearsal_ready' => false,
        'cutover_ready' => false,
        'check_count' => 0,
        'passed_count' => 0,
        'blocked_count' => 0,
    ];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyMigrationReadinessStatus::class,
            'rehearsal_ready' => 'boolean',
            'cutover_ready' => 'boolean',
            'checks' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
