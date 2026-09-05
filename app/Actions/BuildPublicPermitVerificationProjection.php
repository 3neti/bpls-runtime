<?php

namespace App\Actions;

use App\Models\PermitApplication;

class BuildPublicPermitVerificationProjection
{
    public function __construct(
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribePermitReleaseReadiness $describePermitReleaseReadiness,
        private readonly DescribeProvisionalUatPermitCompletion $describeProvisionalCompletion,
    ) {}

    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing([
            'business.owner',
            'lines.lineOfBusiness',
            'provisionalUatPermitCompletion',
            'paymentSchedules.treasuryCollections.receipt',
        ]);
        $verification = $this->describeVerificationBoundary->handle($permitApplication);
        $readiness = $this->describePermitReleaseReadiness->handle($permitApplication);
        $previewCompletion = $this->describeProvisionalCompletion->handle($permitApplication);
        $completion = $permitApplication->provisionalUatPermitCompletion;
        $currentStage = $completion === null
            ? ($readiness['ready_for_authority_review'] ? 'ready_for_authority_review' : $permitApplication->status->value)
            : $completion->status;
        $receipt = $permitApplication->paymentSchedules
            ->flatMap(fn ($schedule) => $schedule->treasuryCollections)
            ->pluck('receipt')->filter()->first();

        return [
            'verification' => [
                ...$verification,
                'legal_release_confirmed' => false,
                'legal_effect_confirmed' => false,
            ],
            'permit' => [
                'application_number' => $permitApplication->application_number,
                'application_year' => $permitApplication->application_year,
                'application_status' => $permitApplication->status->value,
                'current_stage' => $currentStage,
                'business_name' => $permitApplication->business->name,
                'trade_name' => $permitApplication->business->trade_name,
                'permit_number' => $completion?->permit_number,
                'issued_on' => $completion?->issued_at?->toDateString(),
                'valid_until' => $completion?->valid_until?->toDateString(),
                'released_on' => $completion?->released_at?->toDateString(),
                'owner_operator' => $permitApplication->business->owner->name,
                'business_address' => $permitApplication->business->address,
                'lines_of_business' => $permitApplication->lines->map(fn ($line): string => $line->line_of_business_id === null
                    ? (string) data_get($line->metadata, 'line_of_business_name', 'Unresolved')
                    : $line->lineOfBusiness->name)->values()->all(),
                'official_receipt_number' => data_get($receipt, 'receipt_number'),
                'identity_scope' => 'exact_synthetic_permit_identity_only',
                'production_authority' => false,
                'legal_effect' => false,
            ],
            'release_readiness' => [
                'ready_for_authority_review' => $readiness['ready_for_authority_review'],
                'can_release' => $readiness['can_release'],
                'blocked_by' => $readiness['blocked_by'],
                'authority_boundary' => $readiness['authority_boundary'],
                'reason' => $readiness['reason'],
            ],
            'preview_completion' => $previewCompletion,
            'release_status' => [
                'preview_sample' => [
                    'available' => $previewCompletion !== null,
                    'completed' => (bool) data_get($previewCompletion, 'released_in_preview', false),
                    'status' => data_get($previewCompletion, 'status', 'not_available'),
                ],
                'municipal_legal_release' => [
                    'confirmed' => false,
                    'status' => 'not_confirmed',
                ],
                'legal_effect' => [
                    'confirmed' => false,
                    'status' => 'not_confirmed',
                ],
            ],
        ];
    }
}
