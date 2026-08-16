<?php

namespace App\Models;

use App\Enums\MigrationValidationStatus;
use Database\Factories\MigrationValidationResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $legacy_import_batch_id
 * @property string|null $dataset_key
 * @property string $check_key
 * @property MigrationValidationStatus $status
 * @property array<string, mixed>|null $expected
 * @property array<string, mixed>|null $actual
 * @property string|null $details
 */
#[Fillable(['legacy_import_batch_id', 'dataset_key', 'check_key', 'status', 'expected', 'actual', 'details'])]
class MigrationValidationResult extends Model
{
    /** @use HasFactory<MigrationValidationResultFactory> */
    use HasFactory;

    /** @return BelongsTo<LegacyImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MigrationValidationStatus::class,
            'expected' => 'array',
            'actual' => 'array',
        ];
    }
}
