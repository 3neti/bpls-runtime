<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateStaffPermitApplication;
use App\Enums\PermitApplicationType;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StorePermitApplicationRequest;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $permitApplications = PermitApplication::query()
            ->with(['business.owner', 'lines.lineOfBusiness', 'assessments' => fn ($query) => $query->latest()])
            ->latest()
            ->paginate(15)
            ->through(fn (PermitApplication $permitApplication): array => $this->permitApplicationPayload($permitApplication));

        return Inertia::render('permit-applications/Index', [
            'permitApplications' => $permitApplications,
            'can' => [
                'create_permit_applications' => auth()->user()?->can(UserPermission::CreatePermitApplications->value) ?? false,
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(UserPermission::CreatePermitApplications->value);

        return Inertia::render('permit-applications/Create', [
            'currentApplicationYear' => now()->year,
            'applicationTypes' => collect(PermitApplicationType::cases())
                ->map(fn (PermitApplicationType $type): array => [
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                    'value' => $type->value,
                ])
                ->values(),
            'lineOfBusinesses' => LineOfBusiness::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StorePermitApplicationRequest $request, CreateStaffPermitApplication $createPermitApplication): RedirectResponse
    {
        $permitApplication = $createPermitApplication->handle($request->validatedForCreation(), $request->user());

        return to_route('staff.permit-applications.show', $permitApplication);
    }

    public function show(PermitApplication $permitApplication): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $permitApplication->load(['business.owner', 'lines.lineOfBusiness', 'assessments' => fn ($query) => $query->latest()]);

        return Inertia::render('permit-applications/Show', [
            'permitApplication' => $this->permitApplicationPayload($permitApplication),
            'can' => [
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function permitApplicationPayload(PermitApplication $permitApplication): array
    {
        $latestAssessment = $permitApplication->assessments->first();

        return [
            'id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'submitted_at' => $permitApplication->submitted_at?->toIso8601String(),
            'business' => [
                'id' => $permitApplication->business->id,
                'name' => $permitApplication->business->name,
                'trade_name' => $permitApplication->business->trade_name,
                'registration_number' => $permitApplication->business->registration_number,
                'address' => $permitApplication->business->address,
                'barangay' => $permitApplication->business->barangay,
                'owner' => [
                    'id' => $permitApplication->business->owner->id,
                    'name' => $permitApplication->business->owner->name,
                    'email' => $permitApplication->business->owner->email,
                    'phone' => $permitApplication->business->owner->phone,
                    'address' => $permitApplication->business->owner->address,
                ],
            ],
            'lines' => $permitApplication->lines
                ->values()
                ->map(fn ($line): array => [
                    'id' => $line->id,
                    'line_of_business' => [
                        'id' => $line->lineOfBusiness?->id,
                        'name' => $line->lineOfBusiness?->name,
                        'code' => $line->lineOfBusiness?->code,
                    ],
                    'declared_gross_sales_cents' => $line->declared_gross_sales_cents,
                    'capital_investment_cents' => $line->capital_investment_cents,
                    'quantity' => $line->quantity,
                ]),
            'latest_assessment' => $latestAssessment === null ? null : [
                'id' => $latestAssessment->id,
                'sequence' => $latestAssessment->sequence,
                'status' => $latestAssessment->status->value,
                'total_amount_cents' => $latestAssessment->total_amount_cents,
                'assessed_at' => $latestAssessment->assessed_at?->toIso8601String(),
            ],
        ];
    }
}
