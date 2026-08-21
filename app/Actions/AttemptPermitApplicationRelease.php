<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitApplicationStatus;
use App\Exceptions\UnresolvedPermitReleasePolicy;
use App\Models\PermitApplication;
use App\Models\User;

final class AttemptPermitApplicationRelease
{
    public function __construct(private readonly DescribePermitReleaseReadiness $describeReadiness) {}

    public function handle(PermitApplication $permitApplication, ?User $releasedBy = null): never
    {
        $permitApplication->refresh();
        $permitApplication->loadMissing([
            'paymentSchedules.treasuryCollections.receipt',
            'clearances',
        ]);

        $metadata = $permitApplication->metadata ?? [];
        $metadata['release_policy_boundary'] = $this->boundaryEvidence($permitApplication, $releasedBy);

        $permitApplication->forceFill([
            'metadata' => $metadata,
        ])->save();

        throw new UnresolvedPermitReleasePolicy('Permit release is blocked until clearance completion, issuance authority, signatories, QR verification, and the legacy Released status meaning are reconciled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function boundaryEvidence(PermitApplication $permitApplication, ?User $releasedBy): array
    {
        $latestSchedule = $permitApplication->paymentSchedules
            ->sortByDesc('id')
            ->first();
        $receiptCount = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->filter(fn ($collection) => $collection->receipt !== null)
            ->count();

        return [
            'status' => $permitApplication->status->value,
            'payment_schedule_id' => $latestSchedule?->id,
            'payment_schedule_status' => $latestSchedule?->status?->value,
            'is_paid' => $latestSchedule?->status === PaymentScheduleStatus::Paid,
            'receipt_count' => $receiptCount,
            'actor_id' => $releasedBy?->id,
            'blocked_transition' => PermitApplicationStatus::Released->value,
            'readiness' => $this->describeReadiness->handle($permitApplication),
            'reason' => 'Clearance completion is recorded, but permit issuance authority, signatories, public verification, and the meaning of existing release records still require municipal confirmation.',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
