<?php

namespace App\Actions;

use App\Data\Application\ApplicationDataResolver;
use App\Models\PermitApplication;

class BuildPublicPermitVerificationProjection
{
    public function __construct(
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribePermitReleaseReadiness $describePermitReleaseReadiness,
        private readonly DescribeProvisionalUatPermitCompletion $describeProvisionalCompletion,
        private readonly ApplicationDataResolver $applicationDataResolver,
    ) {}

    /** @return array<string, mixed> */
    public function handle(PermitApplication $permitApplication): array
    {
        $permitApplication->loadMissing([
            'business.owner',
            'declaration',
            'lines.lineOfBusiness',
            'treasuryLineOfBusinessAssignments.lineOfBusiness',
            'provisionalUatPermitCompletion',
            'paymentSchedules.treasuryCollections.receipt',
        ]);
        $verification = $this->describeVerificationBoundary->handle($permitApplication);
        $canonicalPermit = $this->applicationDataResolver->resolve($permitApplication)->permit;
        $readiness = $this->describePermitReleaseReadiness->handle($permitApplication);
        $previewCompletion = $this->describeProvisionalCompletion->handle($permitApplication);
        $completion = $permitApplication->provisionalUatPermitCompletion;
        $currentStage = $completion === null
            ? ($readiness['ready_for_authority_review'] ? 'ready_for_authority_review' : $permitApplication->status->value)
            : $completion->status;

        return [
            'verification' => [
                ...$verification,
                'qr_data_url' => $canonicalPermit->verification['qr_data_url'],
                'legal_release_confirmed' => false,
                'legal_effect_confirmed' => false,
            ],
            'permit' => [
                'application_number' => $permitApplication->application_number,
                'application_year' => $permitApplication->application_year,
                'application_status' => $permitApplication->status->value,
                'current_stage' => $currentStage,
                'business_name' => $canonicalPermit->business_name,
                'trade_name' => $permitApplication->business->trade_name,
                'permit_number' => $canonicalPermit->permit_number,
                'issued_on' => $canonicalPermit->issued_on,
                'valid_until' => $canonicalPermit->valid_until,
                'released_on' => $completion?->released_at?->toDateString(),
                'owner_operator' => $canonicalPermit->owner_operator,
                'business_address' => $canonicalPermit->business_address,
                'lines_of_business' => $canonicalPermit->lines_of_business,
                'conditions' => $canonicalPermit->conditions,
                'issuing_authority' => [
                    'office' => $canonicalPermit->issuing_authority['office'],
                    'name' => $canonicalPermit->issuing_authority['name'],
                    'authority_status' => $canonicalPermit->issuing_authority['authority_status'],
                    'signature_applied' => false,
                ],
                'receipt_coverage_confirmed' => $canonicalPermit->official_receipt_bound,
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
