<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateAssessmentForPermitApplication;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationAssessmentController extends Controller
{
    public function index(): Response
    {
        $permitApplications = PermitApplication::query()
            ->with(['business.owner', 'lines.lineOfBusiness', 'assessments' => fn ($query) => $query->latest()])
            ->latest()
            ->paginate(15)
            ->through(fn (PermitApplication $permitApplication): array => [
                'id' => $permitApplication->id,
                'application_number' => $permitApplication->application_number,
                'type' => $permitApplication->type->value,
                'status' => $permitApplication->status->value,
                'application_year' => $permitApplication->application_year,
                'business_name' => $permitApplication->business->name,
                'owner_name' => $permitApplication->business->owner->name,
                'line_count' => $permitApplication->lines->count(),
                'latest_assessment' => $this->latestAssessmentPayload($permitApplication),
            ]);

        return Inertia::render('permit-applications/Assessments/Index', [
            'permitApplications' => $permitApplications,
        ]);
    }

    public function store(PermitApplication $permitApplication, CreateAssessmentForPermitApplication $createAssessment): RedirectResponse
    {
        $assessment = $createAssessment->handle($permitApplication, auth()->user());

        return to_route('staff.permit-applications.assessments.show', $assessment);
    }

    public function show(Assessment $assessment): Response
    {
        $assessment->load([
            'assessedBy',
            'permitApplication.business.owner',
            'permitApplication.lines.lineOfBusiness',
            'lines.lineOfBusiness',
        ]);

        return Inertia::render('permit-applications/Assessments/Show', [
            'assessment' => [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                'assessed_by' => $assessment->assessedBy?->name,
                'total_amount_cents' => $assessment->total_amount_cents,
                'source_snapshot' => $assessment->source_snapshot,
                'permit_application' => [
                    'id' => $assessment->permitApplication->id,
                    'application_number' => $assessment->permitApplication->application_number,
                    'type' => $assessment->permitApplication->type->value,
                    'status' => $assessment->permitApplication->status->value,
                    'application_year' => $assessment->permitApplication->application_year,
                    'business_name' => $assessment->permitApplication->business->name,
                    'owner_name' => $assessment->permitApplication->business->owner->name,
                ],
                'lines' => $assessment->lines
                    ->sortBy('code')
                    ->values()
                    ->map(fn ($line): array => [
                        'id' => $line->id,
                        'code' => $line->code,
                        'name' => $line->name,
                        'category' => $line->category->value,
                        'calculation_type' => $line->calculation_type->value,
                        'basis' => $line->basis,
                        'basis_amount_cents' => $line->basis_amount_cents,
                        'amount_cents' => $line->amount_cents,
                        'line_of_business' => $line->lineOfBusiness?->name,
                        'legal_basis' => $line->legal_basis,
                        'rule_snapshot' => $line->rule_snapshot,
                    ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestAssessmentPayload(PermitApplication $permitApplication): ?array
    {
        $assessment = $permitApplication->assessments->first();

        if (! $assessment instanceof Assessment) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'sequence' => $assessment->sequence,
            'status' => $assessment->status->value,
            'total_amount_cents' => $assessment->total_amount_cents,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
        ];
    }
}
