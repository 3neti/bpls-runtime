<?php

namespace App\Http\Controllers;

use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeProvisionalUatPermitCompletion;
use App\Models\PermitApplication;
use Illuminate\Http\JsonResponse;

class PublicPermitVerificationController extends Controller
{
    public function __invoke(
        DescribePermitVerificationBoundary $describeVerificationBoundary,
        DescribePermitReleaseReadiness $describePermitReleaseReadiness,
        DescribeProvisionalUatPermitCompletion $describeProvisionalCompletion,
        PermitApplication $permitApplication,
        string $verificationCode,
    ): JsonResponse {
        abort_unless($describeVerificationBoundary->matches($permitApplication, $verificationCode), 404);

        $permitApplication->loadMissing('business');
        $verification = $describeVerificationBoundary->handle($permitApplication);
        $readiness = $describePermitReleaseReadiness->handle($permitApplication);

        return response()->json([
            'schema_version' => 'bpls.permit-verification-boundary.v1',
            'verification' => $verification,
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
            'preview_completion' => $describeProvisionalCompletion->handle($permitApplication),
        ]);
    }
}
