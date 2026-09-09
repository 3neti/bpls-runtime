<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LifecycleCleanroomRun $run
 * @property Carbon $expires_at
 * @property Carbon|null $claimed_at
 */
#[Fillable(['lifecycle_cleanroom_run_id', 'token_hash', 'claimed_by_id', 'expires_at', 'claimed_at'])]
class LifecycleCleanroomRegistrationInvitation extends Model
{
    /** @return BelongsTo<LifecycleCleanroomRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(LifecycleCleanroomRun::class, 'lifecycle_cleanroom_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }
}
