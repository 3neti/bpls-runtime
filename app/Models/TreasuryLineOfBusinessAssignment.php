<?php

namespace App\Models;

use Database\Factories\TreasuryLineOfBusinessAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int $line_of_business_id
 * @property int $assigned_by_id
 * @property int $sequence
 * @property string $status
 * @property array<string, mixed> $source_snapshot
 * @property Carbon $assigned_at
 * @property Carbon|null $removed_at
 * @property-read PermitApplication $permitApplication
 * @property-read LineOfBusiness $lineOfBusiness
 * @property-read User $assignedBy
 * @property-read Collection<int, TreasuryLineItem> $items
 */
#[Fillable(['permit_application_id', 'line_of_business_id', 'assigned_by_id', 'sequence', 'status', 'source_snapshot', 'assigned_at', 'removed_at'])]
class TreasuryLineOfBusinessAssignment extends Model
{
    /** @use HasFactory<TreasuryLineOfBusinessAssignmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            if (array_diff(array_keys($assignment->getDirty()), ['status', 'removed_at', 'updated_at']) !== []) {
                throw new LogicException('Treasury Line of Business assignment evidence is immutable.');
            }
        });
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<LineOfBusiness, $this> */
    public function lineOfBusiness(): BelongsTo
    {
        return $this->belongsTo(LineOfBusiness::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /** @return HasMany<TreasuryLineItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TreasuryLineItem::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'assigned_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
