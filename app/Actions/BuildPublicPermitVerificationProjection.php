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
        $permitApplication->loadMissing('business');
        $verification = $this->describeVerificationBoundary->handle($permitApplication);
        $readiness = $this->describePermitReleaseReadiness->handle($permitApplication);
        $previewCompletion = $this->describeProvisionalCompletion->handle($permitApplication);

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
                'business_name' => $permitApplication->business->name,
                'trade_name' => $permitApplication->business->trade_name,
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
