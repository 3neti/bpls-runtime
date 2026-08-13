<?php

namespace App\Models;

use Database\Factories\StoryboardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $created_by_id
 * @property string $title
 * @property string|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['created_by_id', 'title', 'summary'])]
class Storyboard extends Model
{
    /** @use HasFactory<StoryboardFactory> */
    use HasFactory;

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function frames(): HasMany
    {
        return $this->hasMany(StoryboardFrame::class)->orderBy('position');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(StoryboardExport::class)->latest();
    }
}
