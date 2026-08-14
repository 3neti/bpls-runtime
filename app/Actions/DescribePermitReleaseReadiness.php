<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Models\PermitApplication;

class DescribePermitReleaseReadiness
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing([
            'paymentSchedules.treasuryCollections.receipt',
            'clearances',
        ]);

        $latestSchedule = $permitApplication->paymentSchedules
            ->sortByDesc('id')
            ->first();
        $receiptCount = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->filter(fn ($collection) => $collection->receipt !== null)
            ->count();
        $allClearancesCompleted = $permitApplication->clearances->isNotEmpty()
            && $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed);

        $prerequisites = [
            'payment_schedule_paid' => $latestSchedule?->status === PaymentScheduleStatus::Paid,
            'receipt_issued' => $receiptCount > 0,
            'clearances_completed' => $allClearancesCompleted,
            'permit_artifact_available' => true,
        ];
        $readyForAuthorityReview = collect($prerequisites)->every(fn (bool $passed): bool => $passed);

        return [
            'ready_for_authority_review' => $readyForAuthorityReview,
            'can_release' => false,
            'status' => $permitApplication->status->value,
            'prerequisites' => $prerequisites,
            'payment_schedule_id' => $latestSchedule?->id,
            'payment_schedule_status' => $latestSchedule?->status?->value,
            'receipt_count' => $receiptCount,
            'clearances_completed' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(),
            'clearances_total' => $permitApplication->clearances->count(),
            'blocked_by' => [
                'issuance_authority',
                'official_signatories',
                'qr_verification_target',
                'legacy_released_status_semantics',
            ],
            'reason' => 'Payment, receipt, clearance, and permit artifact evidence may be ready for authority review, but actual release remains blocked until issuance authority, official signatories, QR verification target, and legacy Released status semantics are resolved.',
        ];
    }
}
