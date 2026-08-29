<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CompleteBusinessPermitEvaluationResponsibility;
use App\Actions\CorrectEvaluationLinesOfBusiness;
use App\Actions\DescribeBusinessPermitEvaluation;
use App\Actions\InitializeBusinessPermitEvaluation;
use App\Actions\RecordBusinessPermitEvaluationCounterCheck;
use App\Actions\RefreshBusinessPermitEvaluation;
use App\Enums\BusinessPermitEvaluationApplicability;
use App\Enums\BusinessPermitEvaluationSource;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationItem;
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
        Gate::authorize(UserPermission::ViewBusinessPermitEvaluations->value);
        $evaluation = $permitApplication->businessPermitEvaluation()->first();

        return Inertia::render('business-permit-evaluations/Show', [
            'evaluation' => $evaluation instanceof BusinessPermitEvaluation
                ? $describe->handle($evaluation, auth()->user(), 'internal')
                : null,
            'application' => [
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
            ],
            'lineOfBusinesses' => LineOfBusiness::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'can' => $this->capabilities(),
        ]);
    }

    public function initialize(PermitApplication $permitApplication, InitializeBusinessPermitEvaluation $initialize): RedirectResponse
    {
        Gate::authorize(UserPermission::AssessPermitApplications->value);

        return $this->attempt(fn () => $initialize->handle($permitApplication, auth()->user()));
    }

    public function refresh(Request $request, PermitApplication $permitApplication, RefreshBusinessPermitEvaluation $refresh): RedirectResponse
    {
        Gate::authorize(UserPermission::AssessPermitApplications->value);
        $evaluation = $this->evaluation($permitApplication);
        $data = $request->validate([
            'expected_version_sequence' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
        ]);

        return $this->attempt(fn () => $refresh->handle(
            $evaluation,
            auth()->user(),
            $data['expected_version_sequence'],
            $data['expected_fingerprint'],
        ));
    }

    public function confirmResponsibility(
        Request $request,
        PermitApplication $permitApplication,
        BusinessPermitEvaluationItem $item,
        CompleteBusinessPermitEvaluationResponsibility $complete,
    ): RedirectResponse {
        Gate::authorize(UserPermission::ContributeBusinessPermitEvaluations->value);
        $evaluation = $this->evaluation($permitApplication);
        abort_unless($item->business_permit_evaluation_id === $evaluation->id, 404);
        $data = $request->validate([
            'expected_version_sequence' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'applicability' => ['required', Rule::enum(BusinessPermitEvaluationApplicability::class)],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'inspection_mode' => ['nullable', Rule::in(['physical', 'virtual', 'document_review'])],
            'inspection_completed' => ['required', 'boolean'],
            'findings' => ['nullable', 'string', 'max:4000'],
        ]);
        $latestSource = $item->revisions()->latest('id')->value('source_classification');
        $source = BusinessPermitEvaluationSource::tryFrom((string) data_get($item->metadata, 'correction_source_classification'))
            ?? ($latestSource instanceof BusinessPermitEvaluationSource
                ? $latestSource
                : BusinessPermitEvaluationSource::tryFrom((string) $latestSource))
            ?? BusinessPermitEvaluationSource::ProvisionalUat;
        $value = [
            'inspection' => [
                'required' => data_get($item->metadata, 'inspection_required', false),
                'mode' => $data['inspection_mode'] ?? null,
                'completed' => $data['inspection_completed'],
                'findings' => $data['findings'] ?? null,
            ],
        ];
        if (array_key_exists('amount_cents', $data) && $data['amount_cents'] !== null) {
            $value['amount_cents'] = $data['amount_cents'];
        }

        return $this->attempt(fn () => $complete->handle(
            $item,
            auth()->user(),
            BusinessPermitEvaluationApplicability::from($data['applicability']),
            $value,
            $source,
            $data['reason'] ?? null,
            $data['expected_version_sequence'],
            $data['expected_fingerprint'],
            $data['idempotency_key'],
        ));
    }

    public function correctLinesOfBusiness(
        Request $request,
        PermitApplication $permitApplication,
        CorrectEvaluationLinesOfBusiness $correct,
    ): RedirectResponse {
        Gate::authorize(UserPermission::CorrectEvaluationLinesOfBusiness->value);
        $data = $request->validate([
            'line_of_business_ids' => ['required', 'array', 'min:1'],
            'line_of_business_ids.*' => ['required', 'integer', 'distinct', Rule::exists('line_of_businesses', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:2000'],
            'expected_version_sequence' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        return $this->attempt(fn () => $correct->handle(
            $this->evaluation($permitApplication),
            $data['line_of_business_ids'],
            auth()->user(),
            $data['reason'],
            $data['expected_version_sequence'],
            $data['expected_fingerprint'],
            $data['idempotency_key'],
        ));
    }

    public function counterCheck(
        Request $request,
        PermitApplication $permitApplication,
        RecordBusinessPermitEvaluationCounterCheck $counterCheck,
    ): RedirectResponse {
        Gate::authorize(UserPermission::CounterCheckBusinessPermitEvaluations->value);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'expected_version_sequence' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'string', 'size:64'],
        ]);

        return $this->attempt(fn () => $counterCheck->handle(
            $this->evaluation($permitApplication),
            auth()->user(),
            $data['reason'] ?? null,
            $data['expected_version_sequence'],
            $data['expected_fingerprint'],
        ));
    }

    private function evaluation(PermitApplication $permitApplication): BusinessPermitEvaluation
    {
        return $permitApplication->businessPermitEvaluation()->firstOrFail();
    }

    /** @return array<string, bool> */
    private function capabilities(): array
    {
        $user = auth()->user();

        return [
            'initialize' => $user?->can(UserPermission::AssessPermitApplications->value) ?? false,
            'contribute' => $user?->can(UserPermission::ContributeBusinessPermitEvaluations->value) ?? false,
            'counter_check' => $user?->can(UserPermission::CounterCheckBusinessPermitEvaluations->value) ?? false,
            'correct_lines_of_business' => $user?->can(UserPermission::CorrectEvaluationLinesOfBusiness->value) ?? false,
            'prepare_assessment' => $user?->can(UserPermission::AssessPermitApplications->value) ?? false,
        ];
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (LogicException $exception) {
            return back()->withErrors(['evaluation' => $exception->getMessage()]);
        }

        return back();
    }
}
