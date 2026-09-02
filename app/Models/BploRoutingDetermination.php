<?php

namespace App\Models;

use Database\Factories\BploRoutingDeterminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $permit_application_id
 * @property int $determined_by_id
 * @property string $situational_context
 * @property array<string, mixed> $application_facts_snapshot
 * @property Carbon $determined_at
 * @property-read PermitApplication $permitApplication
 * @property-read User $determinedBy
 * @property-read Collection<int, BploRoutingWork> $works
 */
#[Fillable(['permit_application_id', 'determined_by_id', 'situational_context', 'application_facts_snapshot', 'determined_at'])]
class BploRoutingDetermination extends Model
{
    /** @use HasFactory<BploRoutingDeterminationFactory> */
    use HasFactory;

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function determinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'determined_by_id');
    }

    /** @return HasMany<BploRoutingWork, $this> */
    public function works(): HasMany
    {
        return $this->hasMany(BploRoutingWork::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return ['application_facts_snapshot' => 'array', 'determined_at' => 'datetime'];
    }
}
