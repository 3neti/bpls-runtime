<?php

namespace App\Models;

use App\Enums\LegacyDocumentObjectStagingStatus;
use Database\Factories\LegacyDocumentObjectStagingRunFactory;
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
 * @property string $manifest_schema_version
 * @property string $manifest_checksum
 * @property LegacyDocumentObjectStagingStatus $status
 * @property int $object_count
 * @property int $staged_count
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['legacy_import_batch_id', 'run_reference', 'manifest_schema_version', 'manifest_checksum', 'status', 'object_count', 'staged_count', 'started_at', 'completed_at', 'metadata'])]
class LegacyDocumentObjectStagingRun extends Model
{
    /** @use HasFactory<LegacyDocumentObjectStagingRunFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'staging', 'object_count' => 0, 'staged_count' => 0];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return HasMany<LegacyDocumentObjectReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(LegacyDocumentObjectReconciliation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LegacyDocumentObjectStagingStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
