<?php

namespace App\Http\Controllers\Staff;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\CancelPermitApplication;
use App\Actions\CompletePermitClearance;
use App\Actions\CreateStaffPermitApplication;
use App\Actions\DescribeAmendmentPolicyBoundary;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeRenewalPolicyBoundary;
use App\Actions\RenderApplicationFormPdf;
use App\Actions\RenderPermitPdf;
use App\Enums\PermitApplicationType;
use App\Enums\PermitClearanceStatus;
use App\Enums\UserPermission;
use App\Exceptions\UnresolvedPermitReleasePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CancelPermitApplicationRequest;
use App\Http\Requests\Staff\CompletePermitClearanceRequest;
use App\Http\Requests\Staff\StorePermitApplicationRequest;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use App\Models\PermitClearance;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationController extends Controller
{
    public function __construct(
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribeRenewalPolicyBoundary $describeRenewalPolicyBoundary,
        private readonly DescribeAmendmentPolicyBoundary $describeAmendmentPolicyBoundary,
    ) {}

    public function index(): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $permitApplications = PermitApplication::query()
            ->with(['business.owner', 'lines.lineOfBusiness', 'assessments' => fn ($query) => $query->latest(), 'paymentSchedules' => fn ($query) => $query->latest()])
            ->latest()
            ->paginate(15)
            ->through(fn (PermitApplication $permitApplication): array => $this->permitApplicationPayload($permitApplication));

        return Inertia::render('permit-applications/Index', [
            'permitApplications' => $permitApplications,
            'can' => [
                'create_permit_applications' => auth()->user()?->can(UserPermission::CreatePermitApplications->value) ?? false,
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
                'update_permit_application_status' => auth()->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false,
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

    public function cancel(CancelPermitApplicationRequest $request, PermitApplication $permitApplication, CancelPermitApplication $cancelPermitApplication): RedirectResponse
    {
        try {
            $cancelPermitApplication->handle($permitApplication, $request->user(), (string) $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors([
                'status' => $exception->getMessage(),
            ]);
        }

        return to_route('staff.permit-applications.show', $permitApplication)
            ->with('status', 'Permit application cancelled.');
    }

    public function release(PermitApplication $permitApplication, AttemptPermitApplicationRelease $attemptPermitApplicationRelease): RedirectResponse
    {
        Gate::authorize(UserPermission::UpdatePermitApplicationStatus->value);

        try {
            $attemptPermitApplicationRelease->handle($permitApplication, auth()->user());
        } catch (UnresolvedPermitReleasePolicy $exception) {
            return back()->withErrors([
                'release_policy' => $exception->getMessage(),
            ]);
        }

        return to_route('staff.permit-applications.show', $permitApplication);
    }

    public function completeClearance(CompletePermitClearanceRequest $request, PermitApplication $permitApplication, PermitClearance $clearance, CompletePermitClearance $completePermitClearance): RedirectResponse
    {
        abort_unless($clearance->permit_application_id === $permitApplication->id, 404);

        $completePermitClearance->handle($clearance, $request->user(), $request->validated('remarks'));

        return to_route('staff.permit-applications.show', $permitApplication)
            ->with('status', 'Clearance evidence recorded.');
    }

    public function show(PermitApplication $permitApplication): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $permitApplication->load([
            'business.owner',
            'lines.lineOfBusiness',
            'assessments' => fn ($query) => $query->latest(),
            'paymentSchedules' => fn ($query) => $query->latest(),
            'clearances' => fn ($query) => $query->with('completedBy')->orderBy('id'),
        ]);

        return Inertia::render('permit-applications/Show', [
            'permitApplication' => $this->permitApplicationPayload($permitApplication),
            'can' => [
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
                'update_permit_application_status' => auth()->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false,
                'view_permit_documents' => auth()->user()?->can(UserPermission::ViewPermitApplications->value) ?? false,
                'attempt_release' => auth()->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false,
                'complete_clearances' => auth()->user()?->can(UserPermission::CompletePermitClearances->value) ?? false,
            ],
            'permitDocumentGaps' => [
                'Generated application form artifact captures current rescue intake facts only.',
                'Generated permit artifact does not release or issue a permit.',
                'Clearance completion, QR release verification, signatories, and final municipal layout remain unresolved.',
            ],
        ]);
    }

    public function applicationFormPdf(PermitApplication $permitApplication, RenderApplicationFormPdf $renderApplicationFormPdf): HttpResponse
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        return response($renderApplicationFormPdf->handle($permitApplication))
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->applicationFormFilename($permitApplication).'"',
            ]);
    }

    public function permitPdf(PermitApplication $permitApplication, RenderPermitPdf $renderPermitPdf): HttpResponse
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        return response($renderPermitPdf->handle($permitApplication))
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->permitFilename($permitApplication).'"',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function permitApplicationPayload(PermitApplication $permitApplication): array
    {
        $latestAssessment = $permitApplication->assessments->first();
        $latestPaymentSchedule = $permitApplication->paymentSchedules->first();

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
            'latest_payment_schedule' => $latestPaymentSchedule === null ? null : [
                'id' => $latestPaymentSchedule->id,
                'sequence' => $latestPaymentSchedule->sequence,
                'status' => $latestPaymentSchedule->status->value,
                'total_amount_cents' => $latestPaymentSchedule->total_amount_cents,
                'paid_amount_cents' => $latestPaymentSchedule->paid_amount_cents,
            ],
            'terminal_state' => $permitApplication->metadata['terminal_state'] ?? null,
            'renewal_policy_boundary' => $permitApplication->metadata['renewal_policy_boundary']
                ?? $this->describeRenewalPolicyBoundary->handle($permitApplication->type),
            'amendment_policy_boundary' => $permitApplication->metadata['amendment_policy_boundary']
                ?? $this->describeAmendmentPolicyBoundary->handle($permitApplication->type),
            'release_policy_boundary' => $permitApplication->metadata['release_policy_boundary'] ?? null,
            'release_readiness' => $this->describeReleaseReadiness->handle($permitApplication),
            'verification_boundary' => $this->describeVerificationBoundary->handle($permitApplication),
            'clearance_summary' => [
                'completed' => $permitApplication->clearances->where('status', PermitClearanceStatus::Completed)->count(),
                'total' => $permitApplication->clearances->count(),
                'all_completed' => $permitApplication->clearances->isNotEmpty() && $permitApplication->clearances->every(fn (PermitClearance $clearance): bool => $clearance->status === PermitClearanceStatus::Completed),
                'policy_note' => 'Clearance completion records checklist evidence only; release authority remains unresolved.',
            ],
            'clearances' => $permitApplication->clearances
                ->values()
                ->map(fn (PermitClearance $clearance): array => [
                    'id' => $clearance->id,
                    'code' => $clearance->code,
                    'label' => $clearance->label,
                    'status' => $clearance->status->value,
                    'completed_at' => $clearance->completed_at?->toIso8601String(),
                    'completed_by' => $clearance->completedBy === null ? null : [
                        'id' => $clearance->completedBy->id,
                        'name' => $clearance->completedBy->name,
                    ],
                    'remarks' => $clearance->remarks,
                    'policy_note' => $clearance->source_snapshot['policy_note'] ?? null,
                ]),
            'can_continue' => ($permitApplication->metadata['terminal_state']['can_continue'] ?? true) !== false,
        ];
    }

    private function permitFilename(PermitApplication $permitApplication): string
    {
        $label = $permitApplication->application_number ?? 'permit-application-'.$permitApplication->id;
        $safeLabel = str($label)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->lower()
            ->toString();

        return ($safeLabel === '' ? 'permit-application-'.$permitApplication->id : $safeLabel).'-permit.pdf';
    }

    private function applicationFormFilename(PermitApplication $permitApplication): string
    {
        $label = $permitApplication->application_number ?? 'permit-application-'.$permitApplication->id;
        $safeLabel = str($label)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->lower()
            ->toString();

        return ($safeLabel === '' ? 'permit-application-'.$permitApplication->id : $safeLabel).'-application-form.pdf';
    }
}
