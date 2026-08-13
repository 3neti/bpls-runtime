<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $assessed_by_id
 * @property int $sequence
 * @property AssessmentStatus $status
 * @property Carbon|null $assessed_at
 * @property Carbon|null $superseded_at
 * @property int $total_amount_cents
 * @property array<string, mixed> $source_snapshot
 * @property string|null $legacy_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['permit_application_id', 'assessed_by_id', 'sequence', 'status', 'assessed_at', 'superseded_at', 'total_amount_cents', 'source_snapshot', 'legacy_source_id'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'total_amount_cents' => 0,
    ];

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AssessmentLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'assessed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
