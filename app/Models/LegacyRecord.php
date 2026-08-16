<?php

namespace App\Models;

use Database\Factories\LegacyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property int $legacy_source_id
 * @property string $dataset_key
 * @property string $entity_type
 * @property string $legacy_id
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string $status
 * @property int $line_number
 */
#[Fillable(['legacy_import_batch_id', 'legacy_source_id', 'dataset_key', 'entity_type', 'legacy_id', 'payload', 'payload_hash', 'status', 'line_number'])]
class LegacyRecord extends Model
{
    /** @use HasFactory<LegacyRecordFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'staged'];

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return BelongsTo<LegacySource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LegacySource::class, 'legacy_source_id');
    }

    /** @return HasMany<LegacyMigrationException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(LegacyMigrationException::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
