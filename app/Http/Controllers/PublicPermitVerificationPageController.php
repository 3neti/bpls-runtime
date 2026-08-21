<?php

namespace App\Http\Controllers;

use App\Actions\BuildPublicPermitVerificationProjection;
use App\Actions\DescribePermitVerificationBoundary;
use App\Models\PermitApplication;
use Inertia\Inertia;
use Inertia\Response;

class PublicPermitVerificationPageController extends Controller
{
    public function __invoke(
        DescribePermitVerificationBoundary $describeVerificationBoundary,
        BuildPublicPermitVerificationProjection $buildProjection,
        PermitApplication $permitApplication,
        string $verificationCode,
    ): Response {
        abort_unless($describeVerificationBoundary->matches($permitApplication, $verificationCode), 404);

        $projection = $buildProjection->handle($permitApplication);

        return Inertia::render('public/PermitVerification', [
            'verification' => $projection['verification'],
            'permit' => $projection['permit'],
            'releaseReadiness' => $projection['release_readiness'],
            'previewCompletion' => $projection['preview_completion'],
            'releaseStatus' => $projection['release_status'],
        ]);
    }
}
