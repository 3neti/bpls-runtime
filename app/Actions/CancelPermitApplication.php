<?php

namespace App\Actions;

use App\Enums\PermitApplicationStatus;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CancelPermitApplication
{
    public function handle(PermitApplication $permitApplication, User $cancelledBy, string $reason): PermitApplication
    {
        return DB::transaction(function () use ($permitApplication, $cancelledBy, $reason): PermitApplication {
            $permitApplication->refresh();

            if ($permitApplication->status === PermitApplicationStatus::Cancelled) {
                throw new DomainException('This permit application is already cancelled.');
            }

            $metadata = $permitApplication->metadata ?? [];
            $metadata['status_history'] = [
                ...($metadata['status_history'] ?? []),
                [
                    'from' => $permitApplication->status->value,
                    'to' => PermitApplicationStatus::Cancelled->value,
                    'actor_id' => $cancelledBy->id,
                    'reason' => $reason,
                    'occurred_at' => now()->toIso8601String(),
                ],
            ];
            $metadata['terminal_state'] = [
                'status' => PermitApplicationStatus::Cancelled->value,
                'is_terminal' => true,
                'can_continue' => false,
                'reason' => $reason,
                'actor_id' => $cancelledBy->id,
                'occurred_at' => now()->toIso8601String(),
            ];

            $permitApplication->forceFill([
                'status' => PermitApplicationStatus::Cancelled,
                'metadata' => $metadata,
            ])->save();

            return $permitApplication->refresh();
        });
    }
}
