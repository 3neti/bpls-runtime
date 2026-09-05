<?php

namespace App\Actions;

use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Models\PermitApplication;

class DescribePermitReleaseReadiness
{
    public function __construct(private readonly ProjectPermitReadiness $projectPermitReadiness) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(PermitApplication $permitApplication): array
    {
        $isSyntheticLifecycle = data_get($permitApplication->metadata, 'lifecycle_cleanroom.semantic_classification') === 'synthetic_only';
        $readiness = $isSyntheticLifecycle ? $this->projectPermitReadiness->handle($permitApplication) : null;
        $permitApplication->loadMissing(['paymentSchedules.treasuryCollections.receipt', 'clearances', 'provisionalUatPermitCompletion']);
        $syntheticCompletion = $permitApplication->provisionalUatPermitCompletion;

        $latestSchedule = $permitApplication->paymentSchedules
            ->sortByDesc('id')
            ->first();
        $receiptCount = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->filter(fn ($collection) => $collection->receipt !== null)
            ->count();
        $prerequisites = [
            'payment_schedule_paid' => $isSyntheticLifecycle
                ? $readiness['prerequisites']['canonical_collection']
                : $latestSchedule?->status === PaymentScheduleStatus::Paid,
            'receipt_issued' => $isSyntheticLifecycle
                ? $readiness['prerequisites']['issued_official_receipt']
                : $receiptCount > 0,
            'clearances_completed' => $isSyntheticLifecycle
                ? $readiness['prerequisites']['all_required_post_payment_certifications']
                : ($permitApplication->clearances->isNotEmpty()
                    && $permitApplication->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed)),
            'permit_artifact_available' => true,
        ];
        $readyForAuthorityReview = $isSyntheticLifecycle
            ? $readiness['ready']
            : collect($prerequisites)->every(fn (bool $passed): bool => $passed);

        return [
            'ready_for_authority_review' => $readyForAuthorityReview,
            'can_release' => false,
            'status' => $permitApplication->status->value,
            'prerequisites' => $prerequisites,
            'payment_schedule_id' => $latestSchedule?->id,
            'payment_schedule_status' => $latestSchedule?->status?->value,
            'receipt_count' => $isSyntheticLifecycle ? ($readiness['receipt_id'] === null ? 0 : 1) : $receiptCount,
            'clearances_completed' => $isSyntheticLifecycle
                ? count($readiness['certified_offices'])
                : $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(),
            'clearances_total' => $isSyntheticLifecycle ? count($readiness['required_offices']) : $permitApplication->clearances->count(),
            'blocked_by' => [
                'issuance_authority',
                'official_signatories',
                'qr_verification_target',
                'legacy_released_status_semantics',
            ],
            'authority_boundary' => [
                'label' => 'Municipal Review',
                'status' => $readyForAuthorityReview ? 'ready_for_authority_review' : 'awaiting_prerequisites',
                'software_knows' => [
                    'payment_completed' => $prerequisites['payment_schedule_paid'],
                    'receipt_recorded' => $prerequisites['receipt_issued'],
                    'clearances_completed' => $prerequisites['clearances_completed'],
                    'permit_artifact_generated' => $prerequisites['permit_artifact_available'],
                ],
                'human_authority_decides' => [
                    'permit_legally_issued',
                    'permit_released_to_applicant',
                    'permit_legal_effective_date',
                    'qr_public_meaning',
                ],
                'software_records' => [
                    'authority_decision',
                    'issuance_timestamp',
                    'release_timestamp',
                    'effective_period',
                    'qr_verification_status',
                ],
                'artifact_statement' => $isSyntheticLifecycle && $syntheticCompletion?->released_at !== null
                    ? 'The synthetic cleanroom records distinct specimen issuance and BPLO release acts. Neither act establishes production authority, municipal legal release, or legal effect.'
                    : 'The generated permit document supports municipal review but does not issue or release a permit and has no legal effect.',
            ],
            'reason' => $isSyntheticLifecycle && $syntheticCompletion?->released_at !== null
                ? 'The synthetic Permit specimen was issued and separately released in the cleanroom. Production authority, official signatories, legal attestation, and revocation semantics remain uncommissioned.'
                : 'Payment, receipt, clearance, and the generated permit document are ready for review. Municipal release remains unavailable until the responsible authority, official signatories, public verification, and existing release records are confirmed.',
        ];
    }
}
