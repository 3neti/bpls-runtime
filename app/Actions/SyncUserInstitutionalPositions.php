<?php

namespace App\Actions;

use App\Models\InstitutionalPosition;
use App\Models\InstitutionalPositionAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SyncUserInstitutionalPositions
{
    /** @return array<int, InstitutionalPositionAssignment> */
    public function handle(User $user, ?User $assignedBy, string $reason): array
    {
        return DB::transaction(function () use ($user, $assignedBy, $reason): array {
            $roleIds = $user->roles()->pluck('roles.id');
            $desiredPositionIds = InstitutionalPosition::query()
                ->whereIn('capability_role_id', $roleIds)
                ->pluck('id');
            $active = InstitutionalPositionAssignment::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get();

            foreach ($active->whereNotIn('institutional_position_id', $desiredPositionIds) as $assignment) {
                $assignment->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);
            }

            $activePositionIds = $active
                ->whereIn('institutional_position_id', $desiredPositionIds)
                ->pluck('institutional_position_id');

            foreach ($desiredPositionIds->diff($activePositionIds) as $positionId) {
                InstitutionalPositionAssignment::query()->create([
                    'user_id' => $user->id,
                    'institutional_position_id' => $positionId,
                    'assigned_by_id' => $assignedBy?->id,
                    'status' => 'active',
                    'reason' => $reason,
                    'assigned_at' => now(),
                ]);
            }

            return InstitutionalPositionAssignment::query()
                ->with('position.capabilityRole')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->get()
                ->values()
                ->all();
        }, 3);
    }
}
