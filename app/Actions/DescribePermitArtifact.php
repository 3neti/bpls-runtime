<?php

namespace App\Actions;

use App\Models\PermitApplication;

final class DescribePermitArtifact
{
    public function __construct(
        private readonly DescribePermitReleaseReadiness $releaseReadiness,
        private readonly DescribePermitVerificationBoundary $verificationBoundary,
    ) {}

    /**
     * @return array{
     *     label: string,
     *     status: string,
     *     available: bool,
     *     ready_for_authority_review: bool,
     *     can_issue: bool,
     *     can_release: bool,
     *     can_make_legally_effective: bool,
     *     permit_pdf_url: string,
     *     verification_reference: string,
     *     verification_status: string,
     *     verification_url: string,
     *     verification_view_url: string,
     *     authority_boundary_status: string,
     *     artifact_statement: string,
     *     policy_note: string,
     *     blocked_by: list<string>
     * }
     */
    public function handle(PermitApplication $permitApplication): array
    {
        $readiness = $this->releaseReadiness->handle($permitApplication);
        $verification = $this->verificationBoundary->handle($permitApplication);

        return [
            'label' => "Mayor's Permit Artifact",
            'status' => 'generated_artifact_available',
            'available' => true,
            'ready_for_authority_review' => $readiness['ready_for_authority_review'],
            'can_issue' => false,
            'can_release' => false,
            'can_make_legally_effective' => false,
            'permit_pdf_url' => route('staff.permit-applications.permit.pdf', $permitApplication, false),
            'verification_reference' => $verification['reference'],
            'verification_status' => $verification['status'],
            'verification_url' => $verification['url'],
            'verification_view_url' => $verification['view_url'],
            'authority_boundary_status' => $readiness['authority_boundary']['status'],
            'artifact_statement' => $readiness['authority_boundary']['artifact_statement'],
            'policy_note' => 'Generated permit artifacts support authority review only; they do not issue, release, or make a permit legally effective.',
            'blocked_by' => $readiness['blocked_by'],
        ];
    }
}
