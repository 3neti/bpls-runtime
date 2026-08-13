<?php

namespace App\Actions;

use App\Enums\PermitClearanceStatus;
use App\Models\PermitClearance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompletePermitClearance
{
    public function handle(PermitClearance $clearance, User $completedBy, ?string $remarks = null): PermitClearance
    {
        return DB::transaction(function () use ($clearance, $completedBy, $remarks): PermitClearance {
            $clearance = PermitClearance::query()
                ->whereKey($clearance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($clearance->status === PermitClearanceStatus::Completed) {
                return $clearance->load(['completedBy', 'permitApplication']);
            }

            $snapshot = $clearance->source_snapshot ?? [];
            $snapshot['completion'] = [
                'actor_id' => $completedBy->id,
                'status_before' => $clearance->status->value,
                'completed_at' => now()->toIso8601String(),
                'policy_note' => 'Clearance completion records checklist evidence only; it does not release or issue a permit.',
            ];

            $clearance->forceFill([
                'status' => PermitClearanceStatus::Completed,
                'completed_by_id' => $completedBy->id,
                'completed_at' => now(),
                'remarks' => $remarks,
                'source_snapshot' => $snapshot,
            ])->save();

            return $clearance->load(['completedBy', 'permitApplication']);
        });
    }
}
