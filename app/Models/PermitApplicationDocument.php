<?php

namespace App\Models;

use Database\Factories\PermitApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
#[Fillable(['permit_application_id', 'uploaded_by_id', 'media_id', 'label', 'document_type', 'version', 'original_name', 'storage_disk', 'path', 'mime_type', 'size_bytes', 'checksum_sha256', 'remarks', 'source_snapshot', 'uploaded_at', 'removed_at'])]
class PermitApplicationDocument extends Model
{
    /** @use HasFactory<PermitApplicationDocumentFactory> */
    use HasFactory;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'uploaded_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }
}
