<?php

namespace App\Http\Controllers;

use App\Actions\BuildPublicPermitVerificationProjection;
use App\Actions\DescribePermitVerificationBoundary;
use App\Models\PermitApplication;
use Illuminate\Http\JsonResponse;

class PublicPermitVerificationController extends Controller
{
    public function __invoke(
        DescribePermitVerificationBoundary $describeVerificationBoundary,
        BuildPublicPermitVerificationProjection $buildProjection,
        PermitApplication $permitApplication,
        string $verificationCode,
    ): JsonResponse {
        abort_unless($describeVerificationBoundary->matches($permitApplication, $verificationCode), 404);

        $projection = $buildProjection->handle($permitApplication);

        return response()->json([
            'schema_version' => 'bpls.permit-verification-boundary.v2',
            ...$projection,
        ]);
    }
}
