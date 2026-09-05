<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildFeeMatrixQuickLook;
use App\Actions\BuildMunicipalScheduleOfFees;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class FeeMatrixController extends Controller
{
    public function __invoke(
        Request $request,
        BuildFeeMatrixQuickLook $build,
        BuildMunicipalScheduleOfFees $buildSchedule,
    ): JsonResponse {
        abort_unless(Gate::any([
            UserPermission::ViewFeeRules->value,
            UserPermission::ContributeBusinessPermitEvaluations->value,
        ]), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'office' => ['nullable', 'string', 'max:80'],
            'line_of_business_id' => ['nullable', 'integer'],
            'fee_rule_id' => ['nullable', 'integer'],
            'charge_code' => ['nullable', 'string', 'max:120'],
            'charge_label' => ['nullable', 'string', 'max:160'],
            'source_classification' => ['nullable', 'string', 'max:80'],
        ]);

        $matrix = $build->handle(
            $filters['q'] ?? null,
            $filters['office'] ?? null,
            $filters['line_of_business_id'] ?? null,
            $filters['fee_rule_id'] ?? null,
            $filters['charge_code'] ?? null,
            $filters['charge_label'] ?? null,
            $filters['source_classification'] ?? null,
        );

        return response()->json([
            ...$matrix,
            'schedule' => $buildSchedule->fromMatrix($matrix, now(), includeUnconfirmedRules: true),
        ]);
    }
}
