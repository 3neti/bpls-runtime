<?php

namespace App\Http\Controllers\Staff;

use App\Actions\AttemptPermitApplicationRelease;
use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\CancelPermitApplication;
use App\Actions\CompletePermitClearance;
use App\Actions\CreatePermitApplication;
use App\Actions\DescribeAmendmentPolicyBoundary;
use App\Actions\DescribePermitArtifact;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\DescribePermitVerificationBoundary;
use App\Actions\DescribeProvisionalUatPermitCompletion;
use App\Actions\DescribeRenewalPolicyBoundary;
use App\Actions\DescribeRetirementPolicyBoundary;
use App\Actions\DescribeTransferPolicyBoundary;
use App\Actions\RenderApplicationFormPdf;
use App\Actions\RenderPermitPdf;
use App\Enums\PermitApplicationStatus;
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
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationController extends Controller
{
    public function __construct(
        private readonly DescribePermitReleaseReadiness $describeReleaseReadiness,
        private readonly DescribePermitVerificationBoundary $describeVerificationBoundary,
        private readonly DescribePermitArtifact $describePermitArtifact,
        private readonly DescribeRenewalPolicyBoundary $describeRenewalPolicyBoundary,
        private readonly DescribeAmendmentPolicyBoundary $describeAmendmentPolicyBoundary,
        private readonly DescribeTransferPolicyBoundary $describeTransferPolicyBoundary,
        private readonly DescribeRetirementPolicyBoundary $describeRetirementPolicyBoundary,
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
        private readonly DescribeProvisionalUatPermitCompletion $describeProvisionalUatPermitCompletion,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PermitApplicationStatus::class)],
        ]);
        $search = str($filters['q'] ?? '')->trim()->toString();
        $status = $filters['status'] ?? null;

        $permitApplications = PermitApplication::query()
            ->with(['business.owner', 'businessPermitEvaluation', 'lines.lineOfBusiness', 'assessments' => fn ($query) => $query->latest(), 'paymentSchedules' => fn ($query) => $query->latest()])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('application_number', 'like', '%'.$search.'%')
                        ->orWhere('tracking_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('business', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('trade_name', 'like', '%'.$search.'%')
                                ->orWhere('registration_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('business.owner', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PermitApplication $permitApplication): array => $this->permitApplicationPayload($permitApplication));

        return Inertia::render('permit-applications/Index', [
            'permitApplications' => $permitApplications,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statuses' => collect(PermitApplicationStatus::cases())
                ->map(fn (PermitApplicationStatus $status): array => [
                    'label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'value' => $status->value,
                ])
                ->values(),
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
            'intakeAudience' => 'staff',
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

    public function store(StorePermitApplicationRequest $request, CreatePermitApplication $createPermitApplication): RedirectResponse
    {
        $permitApplication = $createPermitApplication->handle($request->validatedForPersistence(), $request->user());

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
            'submittedBy',
            'documents' => fn ($query) => $query->with('uploadedBy')->latest('uploaded_at'),
            'lines.lineOfBusiness',
            'assessments' => fn ($query) => $query
                ->with(['assessedBy', 'treasuryCounterCheck.checkedBy', 'decision.decidedBy'])
                ->latest(),
            'paymentSchedules' => fn ($query) => $query->with('preparedBy')->latest(),
            'treasuryCollections' => fn ($query) => $query->with(['receivedBy', 'receipt.issuedBy'])->oldest(),
            'clearances' => fn ($query) => $query->with('completedBy')->orderBy('id'),
            'officeChargeContributions' => fn ($query) => $query->with('submittedBy')->orderBy('office_code'),
            'provisionalUatPermitCompletion',
            'businessPermitEvaluation',
        ]);

        return Inertia::render('permit-applications/Show', [
            'permitApplication' => $this->permitApplicationPayload($permitApplication, includeTimeline: true),
            'can' => [
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
                'update_permit_application_status' => auth()->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false,
                'view_permit_documents' => auth()->user()?->can(UserPermission::ViewPermitApplications->value) ?? false,
                'attempt_release' => auth()->user()?->can(UserPermission::UpdatePermitApplicationStatus->value) ?? false,
                'complete_clearances' => auth()->user()?->can(UserPermission::CompletePermitClearances->value) ?? false,
                'upload_documents' => $permitApplication->canContinue()
                    && (auth()->user()?->can(UserPermission::CreatePermitApplications->value) ?? false),
                'view_business_permit_evaluation' => auth()->user()?->can(UserPermission::ViewBusinessPermitEvaluations->value) ?? false,
            ],
            'permitDocumentGaps' => [
                'The generated application form shows the intake information currently recorded.',
                'The generated permit document does not release or issue a permit.',
                'Clearance completion, public verification, signatories, and the final municipal layout are not yet confirmed.',
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
    private function permitApplicationPayload(PermitApplication $permitApplication, bool $includeTimeline = false): array
    {
        $latestAssessment = $permitApplication->assessments->first();
        $latestPaymentSchedule = $permitApplication->paymentSchedules->first();

        return [
            'id' => $permitApplication->id,
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'status' => $permitApplication->status->value,
            'application_year' => $permitApplication->application_year,
            'business_permit_evaluation_url' => route('staff.permit-applications.evaluation.show', $permitApplication, absolute: false),
            'has_business_permit_evaluation' => $permitApplication->businessPermitEvaluation !== null,
            'submitted_at' => $permitApplication->submitted_at?->toIso8601String(),
            'business' => [
                'id' => $permitApplication->business->id,
                'name' => $permitApplication->business->name,
                'trade_name' => $permitApplication->business->trade_name,
                'registration_number' => $permitApplication->business->registration_number,
                'address' => $permitApplication->business->address,
                'barangay' => $permitApplication->business->barangay,
                'ownership_type' => $permitApplication->business->ownership_type,
                'organization_name' => $permitApplication->business->organization_name,
                'occupancy' => $permitApplication->business->occupancy,
                'building_name' => $permitApplication->business->building_name,
                'property_index_number' => $permitApplication->business->property_index_number,
                'business_area_square_meters' => $permitApplication->business->business_area_square_meters,
                'male_employee_count' => $permitApplication->business->male_employee_count,
                'female_employee_count' => $permitApplication->business->female_employee_count,
                'contact_number' => $permitApplication->business->contact_number,
                'email' => $permitApplication->business->email,
                'established_on' => $permitApplication->business->established_on?->toDateString(),
                'started_on' => $permitApplication->business->started_on?->toDateString(),
                'registered_on' => $permitApplication->business->registered_on?->toDateString(),
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
                    'started_on' => $line->started_on?->toDateString(),
                ]),
            'latest_assessment' => $latestAssessment === null ? null : [
                'id' => $latestAssessment->id,
                'sequence' => $latestAssessment->sequence,
                'status' => $latestAssessment->status->value,
                'total_amount_cents' => $latestAssessment->total_amount_cents,
                'assessed_at' => $latestAssessment->assessed_at?->toIso8601String(),
                'treasury_counter_check' => $latestAssessment->treasuryCounterCheck === null ? null : [
                    'result' => $latestAssessment->treasuryCounterCheck->result?->value,
                    'checked_at' => $latestAssessment->treasuryCounterCheck->checked_at->toIso8601String(),
                ],
                'treasurer_decision' => $latestAssessment->decision === null ? null : [
                    'action' => $latestAssessment->decision->action->value,
                    'decided_at' => $latestAssessment->decision->decided_at->toIso8601String(),
                ],
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
            'transfer_policy_boundary' => $permitApplication->metadata['transfer_policy_boundary']
                ?? $this->describeTransferPolicyBoundary->handle($permitApplication->type),
            'retirement_policy_boundary' => $permitApplication->metadata['retirement_policy_boundary']
                ?? $this->describeRetirementPolicyBoundary->handle($permitApplication->type),
            'release_policy_boundary' => $permitApplication->metadata['release_policy_boundary'] ?? null,
            'permit_artifact' => $this->describePermitArtifact->handle($permitApplication),
            'release_readiness' => $this->describeReleaseReadiness->handle($permitApplication),
            'verification_boundary' => $this->describeVerificationBoundary->handle($permitApplication),
            ...($includeTimeline ? ['provisional_uat_completion' => $this->describeProvisionalUatPermitCompletion->handle($permitApplication)] : []),
            ...($includeTimeline ? ['office_charge_contributions' => $permitApplication->officeChargeContributions
                ->map(fn ($contribution): array => [
                    'office_label' => $contribution->office_label,
                    'is_applicable' => $contribution->is_applicable,
                    'status' => $contribution->status,
                    'amount_cents' => $contribution->amount_cents,
                    'submitted_by' => $contribution->submittedBy?->name,
                    'submitted_at' => $contribution->submitted_at?->toIso8601String(),
                    'semantic_classification' => $contribution->semantic_classification,
                ])
                ->values()] : []),
            ...($includeTimeline ? ['timeline' => $this->buildPermitApplicationTimeline->handle($permitApplication)] : []),
            ...($includeTimeline ? ['documents' => $permitApplication->documents
                ->values()
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'label' => $document->label,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'remarks' => $document->remarks,
                    'uploaded_at' => $document->uploaded_at->toIso8601String(),
                    'uploaded_by' => $document->uploadedBy?->name,
                    'download_url' => route('staff.permit-applications.documents.download', [$permitApplication, $document], false),
                    'policy_note' => $document->source_snapshot['policy_note'] ?? null,
                ])] : []),
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
            'can_continue' => $permitApplication->canContinue(),
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
