<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\CorrectOwnEvaluationLinesOfBusiness;
use App\Actions\DescribeBusinessPermitEvaluation;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class BusinessPermitEvaluationController extends Controller
{
    public function show(PermitApplication $permitApplication, DescribeBusinessPermitEvaluation $describe): Response
    {
        Gate::authorize(UserPermission::ViewOwnBusinessPermitEvaluations->value);
        $this->authorizeOwner($permitApplication);
        $evaluation = $permitApplication->businessPermitEvaluation()->firstOrFail();

        return Inertia::render('business-permit-evaluations/Show', [
            'evaluation' => $describe->handle($evaluation, auth()->user(), 'citizen'),
            'application' => ['id' => $permitApplication->id, 'application_number' => $permitApplication->application_number],
            'lineOfBusinesses' => LineOfBusiness::query()->availableToMunicipalCatalog()->orderBy('name')->get(['id', 'code', 'name']),
            'can' => [
                'initialize' => false,
                'contribute' => false,
                'counter_check' => false,
                'correct_lines_of_business' => auth()->user()?->can(UserPermission::CorrectOwnEvaluationDeclarations->value) ?? false,
                'prepare_assessment' => false,
            ],
        ]);
    }

    public function correctLinesOfBusiness(
        Request $request,
        PermitApplication $permitApplication,
        CorrectOwnEvaluationLinesOfBusiness $correct,
    ): RedirectResponse {
        Gate::authorize(UserPermission::CorrectOwnEvaluationDeclarations->value);
        $this->authorizeOwner($permitApplication);
        $data = $request->validate([
            'line_of_business_ids' => ['required', 'array', 'min:1'],
            'line_of_business_ids.*' => ['required', 'integer', 'distinct', Rule::exists('line_of_businesses', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('metadata->scenario_id'))],
            'reason' => ['required', 'string', 'max:2000'],
            'expected_version_sequence' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
        $evaluation = $permitApplication->businessPermitEvaluation()->firstOrFail();

        try {
            $correct->handle(
                $evaluation,
                $data['line_of_business_ids'],
                auth()->user(),
                $data['reason'],
                $data['expected_version_sequence'],
                $data['expected_fingerprint'],
                $data['idempotency_key'],
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['evaluation' => $exception->getMessage()]);
        }

        return back();
    }

    private function authorizeOwner(PermitApplication $permitApplication): void
    {
        abort_unless(
            PermitApplication::query()
                ->whereKey($permitApplication)
                ->visibleToPortalOwner(auth()->user())
                ->exists(),
            403,
        );
    }
}
