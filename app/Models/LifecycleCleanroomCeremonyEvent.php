<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $sequence
 * @property string|null $actor_key
 * @property string $event
 * @property string|null $route_name
 * @property string|null $canonical_step
 * @property int $completed_stage_count
 * @property Carbon $occurred_at
 */
#[Fillable(['lifecycle_cleanroom_run_id', 'actor_user_id', 'permit_application_id', 'sequence', 'actor_key', 'event', 'route_name', 'canonical_step', 'completed_stage_count', 'context', 'occurred_at'])]
class LifecycleCleanroomCeremonyEvent extends Model
{
    /** @return BelongsTo<LifecycleCleanroomRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(LifecycleCleanroomRun::class, 'lifecycle_cleanroom_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function permitApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
