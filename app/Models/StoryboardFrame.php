<?php

namespace App\Models;

use Database\Factories\StoryboardFrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $storyboard_id
 * @property int $position
 * @property string $title
 * @property string|null $image_path
 * @property string|null $description
 * @property string|null $dialogue
 * @property int $duration_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['storyboard_id', 'position', 'title', 'image_path', 'description', 'dialogue', 'duration_seconds'])]
class StoryboardFrame extends Model
{
    /** @use HasFactory<StoryboardFrameFactory> */
    use HasFactory;

    public function storyboard(): BelongsTo
    {
        return $this->belongsTo(Storyboard::class);
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
