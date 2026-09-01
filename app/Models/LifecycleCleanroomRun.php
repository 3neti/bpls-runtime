<?php

namespace App\Models;

use Database\Factories\LifecycleCleanroomRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $status
 * @property string|null $target_step
 * @property int $started_by_id
 * @property int|null $new_application_id
 * @property int|null $renewal_application_id
 * @property array<string, mixed> $actor_manifest
 * @property array<string, mixed> $owned_resource_manifest
 * @property Carbon|null $closed_at
 */
#[Fillable(['public_id', 'status', 'target_step', 'started_by_id', 'new_application_id', 'renewal_application_id', 'actor_manifest', 'owned_resource_manifest', 'closed_at'])]
class LifecycleCleanroomRun extends Model
{
    /** @use HasFactory<LifecycleCleanroomRunFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function newApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class, 'new_application_id');
    }

    /** @return BelongsTo<PermitApplication, $this> */
    public function renewalApplication(): BelongsTo
    {
        return $this->belongsTo(PermitApplication::class, 'renewal_application_id');
    }

    /** @return array<string, array{label: string, user_id: int, role_id: int}> */
    public function actors(): array
    {
        $actors = $this->actor_manifest['actors'] ?? null;
        if (! is_array($actors)) {
            return [];
        }

        $normalized = [];
        foreach ($actors as $key => $actor) {
            if (is_string($key) && is_array($actor)
                && is_string($actor['label'] ?? null)
                && is_int($actor['user_id'] ?? null)
                && is_int($actor['role_id'] ?? null)) {
                $normalized[$key] = ['label' => $actor['label'], 'user_id' => $actor['user_id'], 'role_id' => $actor['role_id']];
            }
        }

        return $normalized;
    }

    /** @return array{label: string, user_id: int, role_id: int}|null */
    public function actor(string $key): ?array
    {
        return $this->actors()[$key] ?? null;
    }

    /** @return list<int> */
    public function ownedPermitApplicationIds(): array
    {
        $ids = $this->owned_resource_manifest['permit_application_ids'] ?? null;
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, is_int(...)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_manifest' => 'array',
            'owned_resource_manifest' => 'array',
            'closed_at' => 'datetime',
        ];
    }
}
