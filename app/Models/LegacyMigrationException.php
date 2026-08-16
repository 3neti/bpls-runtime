<?php

namespace App\Models;

use App\Enums\MigrationExceptionSeverity;
use App\Enums\MigrationExceptionStatus;
use Database\Factories\LegacyMigrationExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property int|null $legacy_record_id
 * @property string|null $dataset_key
 * @property int|null $line_number
 * @property string $code
 * @property MigrationExceptionSeverity $severity
 * @property MigrationExceptionStatus $status
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property int|null $resolved_by_id
 * @property Carbon|null $resolved_at
 */
#[Fillable(['legacy_import_batch_id', 'legacy_record_id', 'dataset_key', 'line_number', 'code', 'severity', 'status', 'message', 'context', 'resolved_by_id', 'resolved_at'])]
class LegacyMigrationException extends Model
{
    /** @use HasFactory<LegacyMigrationExceptionFactory> */
    use HasFactory;

    protected $table = 'migration_exceptions';

    protected $attributes = ['status' => 'open'];

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

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => MigrationExceptionSeverity::class,
            'status' => MigrationExceptionStatus::class,
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
