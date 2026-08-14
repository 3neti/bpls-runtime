<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\CreatePermitApplication;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StorePermitApplicationRequest;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        $permitApplications = PermitApplication::query()
            ->whereBelongsTo($request->user(), 'submittedBy')
            ->with(['business', 'lines.lineOfBusiness'])
            ->latest('id')
            ->paginate(15)
            ->through(fn (PermitApplication $permitApplication): array => $this->summaryPayload($permitApplication));

        return Inertia::render('citizen/permit-applications/Index', [
            'permitApplications' => $permitApplications,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(UserPermission::CreateOwnPermitApplications->value);

        return Inertia::render('permit-applications/Create', [
            'intakeAudience' => 'citizen',
            'currentApplicationYear' => now()->year,
            'applicationTypes' => [[
                'label' => 'New',
                'value' => PermitApplicationType::New->value,
            ]],
            'lineOfBusinesses' => LineOfBusiness::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'applicant' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function store(StorePermitApplicationRequest $request, CreatePermitApplication $createPermitApplication): RedirectResponse
    {
        $permitApplication = $createPermitApplication->handle($request->validatedForCreation(), $request->user());

        return to_route('citizen.permit-applications.show', $permitApplication)
            ->with('status', 'Permit application draft saved.');
    }

    public function show(Request $request, int $permitApplication): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        $application = PermitApplication::query()
            ->whereKey($permitApplication)
            ->whereBelongsTo($request->user(), 'submittedBy')
            ->with(['business.owner', 'lines.lineOfBusiness'])
            ->withExists('assessments')
            ->firstOrFail();

        $isDraft = $application->status === PermitApplicationStatus::Draft;
        $assessmentStarted = (bool) $application->assessments_exists;

        return Inertia::render('citizen/permit-applications/Show', [
            'permitApplication' => [
                ...$this->summaryPayload($application),
                'application_year' => $application->application_year,
                'owner' => [
                    'name' => $application->business->owner->name,
                    'email' => $application->business->owner->email,
                    'phone' => $application->business->owner->phone,
                    'address' => $application->business->owner->address,
                ],
                'business' => [
                    'name' => $application->business->name,
                    'trade_name' => $application->business->trade_name,
                    'registration_number' => $application->business->registration_number,
                    'address' => $application->business->address,
                    'barangay' => $application->business->barangay,
                ],
                'lines' => $application->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'line_of_business' => [
                        'code' => $line->lineOfBusiness?->code,
                        'name' => $line->lineOfBusiness?->name,
                    ],
                    'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                    'capital_investment_cents' => $line->capital_investment_cents,
                    'quantity' => $line->quantity,
                    'started_on' => $line->started_on?->toDateString(),
                ])->values(),
                'draft_boundary' => [
                    'is_draft' => $isDraft,
                    'assessment_started' => $assessmentStarted,
                    'official_application_number_assigned' => $application->application_number !== null,
                    'statement' => $isDraft && ! $assessmentStarted
                        ? 'This record is a saved citizen draft. It has not been submitted for assessment or accepted as an official permit application.'
                        : 'This application has entered municipal processing. Its displayed status reflects the current authoritative application record.',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(PermitApplication $permitApplication): array
    {
        return [
            'id' => $permitApplication->id,
            'display_reference' => $permitApplication->application_number ?? 'Draft #'.$permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'status' => $permitApplication->status->value,
            'business_name' => $permitApplication->business->name,
            'activity_count' => $permitApplication->lines->count(),
            'saved_at' => $permitApplication->created_at?->toIso8601String(),
        ];
    }
}
