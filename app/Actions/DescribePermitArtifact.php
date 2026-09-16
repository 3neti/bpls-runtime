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
     *     permit_pdf_url: string|null,
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
        $available = $readiness['prerequisites']['permit_artifact_available'] === true;

        return [
            'label' => "Mayor's Permit Preview",
            'status' => $available ? 'issued_document_available' : 'not_issued',
            'available' => $available,
            'ready_for_authority_review' => $readiness['ready_for_authority_review'],
            'can_issue' => false,
            'can_release' => false,
            'can_make_legally_effective' => false,
            'permit_pdf_url' => $available ? route('staff.permit-applications.permit.pdf', $permitApplication, false) : null,
            'verification_reference' => $verification['reference'],
            'verification_status' => $verification['status'],
            'verification_url' => $verification['url'],
            'verification_view_url' => $verification['view_url'],
            'authority_boundary_status' => $readiness['authority_boundary']['status'],
            'artifact_statement' => $readiness['authority_boundary']['artifact_statement'],
            'policy_note' => $available
                ? 'This issued document is a synthetic UAT artifact and has no production legal effect.'
                : 'The Business Permit is not yet issued. The verification reference alone does not create a permit document.',
            'blocked_by' => $readiness['blocked_by'],
        ];
    }
}
