<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildFeeMatrixQuickLook;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class FeeMatrixController extends Controller
{
    public function __invoke(Request $request, BuildFeeMatrixQuickLook $build): JsonResponse
    {
        abort_unless(Gate::any([
            UserPermission::ViewFeeRules->value,
            UserPermission::ContributeBusinessPermitEvaluations->value,
        ]), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'office' => ['nullable', 'string', 'max:80'],
            'line_of_business_id' => ['nullable', 'integer'],
        ]);

        return response()->json($build->handle(
            $filters['q'] ?? null,
            $filters['office'] ?? null,
            $filters['line_of_business_id'] ?? null,
        ));
    }
}
