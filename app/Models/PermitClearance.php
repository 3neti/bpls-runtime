<?php

namespace App\Models;

use App\Enums\PermitClearanceStatus;
use Database\Factories\PermitClearanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int|null $completed_by_id
 * @property string $code
 * @property string $label
 * @property PermitClearanceStatus $status
 * @property Carbon|null $completed_at
 * @property string|null $remarks
 * @property array<string, mixed> $source_snapshot
 * @property string|null $legacy_source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['permit_application_id', 'completed_by_id', 'code', 'label', 'status', 'completed_at', 'remarks', 'source_snapshot', 'legacy_source_id'])]
class PermitClearance extends Model
{
    /** @use HasFactory<PermitClearanceFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PermitClearanceStatus::class,
            'completed_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
