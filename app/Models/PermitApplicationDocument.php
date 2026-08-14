<?php

namespace App\Models;

use Database\Factories\PermitApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $uploaded_by_id
 * @property string $label
 * @property string $original_name
 * @property string $storage_disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $remarks
 * @property array<string, mixed> $source_snapshot
 * @property Carbon $uploaded_at
 */
#[Fillable(['permit_application_id', 'uploaded_by_id', 'label', 'original_name', 'storage_disk', 'path', 'mime_type', 'size_bytes', 'remarks', 'source_snapshot', 'uploaded_at'])]
class PermitApplicationDocument extends Model
{
    /** @use HasFactory<PermitApplicationDocumentFactory> */
    use HasFactory;

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }
}
