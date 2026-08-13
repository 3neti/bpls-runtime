<?php

namespace App\Models;

use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use Database\Factories\StoryboardExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $storyboard_id
 * @property StoryboardExportFormat $format
 * @property StoryboardExportStatus $status
 * @property string|null $path
 * @property string|null $failure_message
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['storyboard_id', 'format', 'status', 'path', 'failure_message', 'completed_at'])]
class StoryboardExport extends Model
{
    /** @use HasFactory<StoryboardExportFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    public function storyboard(): BelongsTo
    {
        return $this->belongsTo(Storyboard::class);
    }

    public function downloadUrl(): ?string
    {
        if ($this->path === null || $this->status !== StoryboardExportStatus::Completed) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => StoryboardExportFormat::class,
            'status' => StoryboardExportStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
