<?php

namespace App\Http\Controllers\Citizen;

use App\Actions\BuildPermitApplicationTimeline;
use App\Actions\CreateCitizenPermitApplicationDraft;
use App\Actions\DescribeOnlinePaymentBoundary;
use App\Actions\DescribePaymentPolicyBoundary;
use App\Actions\DescribePermitArtifact;
use App\Actions\DescribePermitReleaseReadiness;
use App\Actions\SubmitCitizenPermitApplication;
use App\Actions\UpdateCitizenPermitApplicationDraft;
use App\Enums\PermitApplicationStatus;
use App\Enums\PermitApplicationType;
use App\Enums\PermitClearanceStatus;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\StorePermitApplicationRequest;
use App\Http\Requests\Citizen\SubmitPermitApplicationRequest;
use App\Http\Requests\Citizen\UpdatePermitApplicationRequest;
use App\Models\LineOfBusiness;
use App\Models\PermitApplication;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationController extends Controller
{
    public function __construct(
        private readonly BuildPermitApplicationTimeline $buildPermitApplicationTimeline,
        private readonly DescribeOnlinePaymentBoundary $describeOnlinePaymentBoundary,
        private readonly DescribePaymentPolicyBoundary $describePaymentPolicyBoundary,
        private readonly DescribePermitArtifact $describePermitArtifact,
        private readonly DescribePermitReleaseReadiness $describePermitReleaseReadiness,
    ) {}

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
            'registry' => $this->registryPayload($request),
        ]);
    }

    public function store(StorePermitApplicationRequest $request, CreateCitizenPermitApplicationDraft $createDraft): RedirectResponse
    {
        try {
            $permitApplication = $createDraft->handle($request->validatedForPersistence(), $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['business_id' => $exception->getMessage()]);
        }

        return to_route('citizen.permit-applications.show', $permitApplication)
            ->with('status', 'Permit application draft saved.');
    }

    public function edit(Request $request, int $permitApplication): Response
    {
        Gate::authorize(UserPermission::EditOwnPermitApplications->value);

        $application = $this->ownedApplication($request, $permitApplication);
        abort_unless($this->isEditableDraft($application), 409, 'This permit application has entered municipal processing and may no longer be edited as a citizen draft.');

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
            'registry' => $this->registryPayload($request),
            'draft' => $this->draftIntakePayload($application),
        ]);
    }

    public function update(
        UpdatePermitApplicationRequest $request,
        int $permitApplication,
        UpdateCitizenPermitApplicationDraft $updateDraft,
    ): RedirectResponse {
        $application = $this->ownedApplication($request, $permitApplication);

        try {
            $application = $updateDraft->handle($application, $request->validatedForPersistence(), $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['draft' => $exception->getMessage()]);
        }

        return to_route('citizen.permit-applications.show', $application)
            ->with('status', 'Permit application draft updated.');
    }

    public function submit(
        SubmitPermitApplicationRequest $request,
        int $permitApplication,
        SubmitCitizenPermitApplication $submitApplication,
    ): RedirectResponse {
        $application = $this->ownedApplication($request, $permitApplication);

        try {
            $application = $submitApplication->handle($application, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['submission' => $exception->getMessage()]);
        }

        return to_route('citizen.permit-applications.show', $application)
            ->with('status', 'Permit application submitted and received for municipal processing.');
    }

    public function show(Request $request, int $permitApplication): Response
    {
        Gate::authorize(UserPermission::ViewOwnPermitApplications->value);

        $application = $this->ownedApplication($request, $permitApplication);

        $isDraft = $application->status === PermitApplicationStatus::Draft;
        $assessmentStarted = (bool) $application->assessments_exists;
        $canViewDocuments = $request->user()->can(UserPermission::ViewOwnPermitApplicationDocuments->value);
        $canViewFinancials = $request->user()->can(UserPermission::ViewOwnPermitApplicationFinancials->value);
        $latestAssessment = $canViewFinancials ? $application->assessments->first() : null;
        $latestPaymentSchedule = $latestAssessment === null
            ? null
            : $application->paymentSchedules->firstWhere('assessment_id', $latestAssessment->id);
        $latestCollection = $latestPaymentSchedule?->treasuryCollections->sortByDesc('id')->first();
        $latestReceipt = $latestCollection?->receipt;
        $releaseReadiness = $canViewFinancials ? $this->describePermitReleaseReadiness->handle($application) : null;
        $authorityReview = $releaseReadiness !== null
            && $releaseReadiness['payment_schedule_id'] === $latestPaymentSchedule?->id
                ? $releaseReadiness
                : null;
        $permitArtifact = ($authorityReview['ready_for_authority_review'] ?? false)
            ? $this->describePermitArtifact->handle($application)
            : null;

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
                'documents' => $canViewDocuments
                    ? $application->documents->map(fn ($document): array => [
                        'id' => $document->id,
                        'label' => $document->label,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'remarks' => $document->remarks,
                        'uploaded_at' => $document->uploaded_at->toIso8601String(),
                        'uploaded_by' => $document->uploaded_by_id === $request->user()->id
                            ? 'You'
                            : 'Municipal staff',
                    ])->values()
                    : [],
                'documentary_readiness' => [
                    'received_document_count' => $canViewDocuments ? $application->documents->count() : 0,
                    'requirement_catalog_status' => 'unresolved',
                    'submission_readiness' => 'not_determined',
                    'statement' => 'Documents are retained as supporting evidence. Their statutory sufficiency and the requirements for formal submission have not yet been determined.',
                ],
                'draft_boundary' => [
                    'is_draft' => $isDraft,
                    'assessment_started' => $assessmentStarted,
                    'official_application_number_assigned' => $application->application_number !== null,
                    'statement' => $isDraft && ! $assessmentStarted
                        ? 'This record is a saved citizen draft. It has not been submitted for assessment or accepted as an official permit application.'
                        : 'This application has entered municipal processing. Its displayed status reflects the current authoritative application record.',
                ],
                'submission_boundary' => [
                    'citizen_submitted_at' => data_get($application->metadata, 'citizen_submission.submitted_at'),
                    'municipality_received_at' => data_get($application->metadata, 'municipal_receipt.received_at'),
                    'documentary_sufficiency_determined' => (bool) data_get($application->metadata, 'submission_policy_boundary.documentary_sufficiency_determined', false),
                    'statement' => $isDraft
                        ? 'Formal submission places this draft in the municipal processing queue. It does not confirm documentary sufficiency, approval, assessment acceptance, payment, or permit issuance.'
                        : 'The citizen submitted this application and the municipality received it into the processing queue. Later determinations remain separate municipal actions.',
                ],
                'processing' => [
                    'has_entered_municipal_processing' => ! $isDraft
                        || $application->application_number !== null
                        || $assessmentStarted,
                    'application_status' => $application->status->value,
                    'statement' => 'This view reports the current municipal processing record. Submission does not authorize online payment or determine documentary sufficiency.',
                    'assessment' => $latestAssessment === null ? null : [
                        'id' => $latestAssessment->id,
                        'sequence' => $latestAssessment->sequence,
                        'status' => $latestAssessment->status->value,
                        'total_amount_cents' => $latestAssessment->total_amount_cents,
                        'assessed_at' => $latestAssessment->assessed_at?->toIso8601String(),
                    ],
                    'payment_schedule' => $latestPaymentSchedule === null ? null : [
                        'id' => $latestPaymentSchedule->id,
                        'sequence' => $latestPaymentSchedule->sequence,
                        'status' => $latestPaymentSchedule->status->value,
                        'payment_mode' => $latestPaymentSchedule->payment_mode,
                        'due_on' => $latestPaymentSchedule->due_on?->toDateString(),
                        'total_amount_cents' => $latestPaymentSchedule->total_amount_cents,
                        'paid_amount_cents' => $latestPaymentSchedule->paid_amount_cents,
                        'balance_amount_cents' => max(0, $latestPaymentSchedule->total_amount_cents - $latestPaymentSchedule->paid_amount_cents),
                        'payment_policy_boundary' => $this->describePaymentPolicyBoundary->handle($latestPaymentSchedule),
                        'online_payment_boundary' => $this->describeOnlinePaymentBoundary->handle($latestPaymentSchedule),
                    ],
                    'collection' => $latestCollection === null ? null : [
                        'id' => $latestCollection->id,
                        'status' => $latestCollection->status->value,
                        'channel' => $latestCollection->channel->value,
                        'method' => $latestCollection->method->value,
                        'amount_cents' => $latestCollection->amount_cents,
                        'received_at' => $latestCollection->received_at?->toIso8601String(),
                        'receipt' => $latestReceipt === null ? null : [
                            'id' => $latestReceipt->id,
                            'receipt_number' => $latestReceipt->receipt_number,
                            'status' => $latestReceipt->status->value,
                            'numbering_authority' => $latestReceipt->numbering_authority,
                            'amount_cents' => $latestReceipt->amount_cents,
                            'issued_at' => $latestReceipt->issued_at?->toIso8601String(),
                        ],
                    ],
                    'clearance_summary' => [
                        'completed' => $application->clearances->where('status', PermitClearanceStatus::Completed)->count(),
                        'total' => $application->clearances->count(),
                        'all_completed' => $application->clearances->isNotEmpty()
                            && $application->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed),
                        'items' => $application->clearances->map(fn ($clearance): array => [
                            'id' => $clearance->id,
                            'code' => $clearance->code,
                            'label' => $clearance->label,
                            'status' => $clearance->status->value,
                            'completed_at' => $clearance->completed_at?->toIso8601String(),
                        ])->values(),
                    ],
                    'authority_review' => $authorityReview === null ? null : [
                        'ready_for_authority_review' => $authorityReview['ready_for_authority_review'],
                        'can_release' => $authorityReview['can_release'],
                        'status' => $authorityReview['authority_boundary']['status'],
                        'prerequisites' => $authorityReview['prerequisites'],
                        'statement' => $authorityReview['authority_boundary']['artifact_statement'],
                        'reason' => $authorityReview['reason'],
                    ],
                ],
                'permit_artifact' => $permitArtifact === null ? null : [
                    'label' => $permitArtifact['label'],
                    'status' => $permitArtifact['status'],
                    'available' => $permitArtifact['available'],
                    'ready_for_authority_review' => $permitArtifact['ready_for_authority_review'],
                    'can_issue' => $permitArtifact['can_issue'],
                    'can_release' => $permitArtifact['can_release'],
                    'can_make_legally_effective' => $permitArtifact['can_make_legally_effective'],
                    'verification_reference' => $permitArtifact['verification_reference'],
                    'verification_status' => $permitArtifact['verification_status'],
                    'verification_view_url' => $permitArtifact['verification_view_url'],
                    'artifact_statement' => $permitArtifact['artifact_statement'],
                    'policy_note' => $permitArtifact['policy_note'],
                    'blocked_by' => $permitArtifact['blocked_by'],
                ],
                'timeline' => $this->citizenTimeline($application, $canViewDocuments, $canViewFinancials),
                'can_edit' => $request->user()->can(UserPermission::EditOwnPermitApplications->value)
                    && $this->isEditableDraft($application),
                'can_submit' => $request->user()->can(UserPermission::SubmitOwnPermitApplications->value)
                    && $this->isSubmittableDraft($application, $request->user()->business_owner_id),
                'can_upload_documents' => $request->user()->can(UserPermission::UploadOwnPermitApplicationDocuments->value)
                    && $this->isCitizenDocumentUploadAvailable($application),
                'can_view_documents' => $canViewDocuments,
                'can_view_financials' => $canViewFinancials,
            ],
        ]);
    }

    private function ownedApplication(Request $request, int $permitApplication): PermitApplication
    {
        return PermitApplication::query()
            ->whereKey($permitApplication)
            ->whereBelongsTo($request->user(), 'submittedBy')
            ->with([
                'business.owner',
                'submittedBy',
                'lines.lineOfBusiness',
                'documents' => fn ($query) => $query->latest('uploaded_at')->latest('id'),
                'assessments' => fn ($query) => $query->whereNull('superseded_at')->latest('sequence'),
                'paymentSchedules' => fn ($query) => $query->latest('sequence'),
                'paymentSchedules.treasuryCollections' => fn ($query) => $query->oldest('id'),
                'paymentSchedules.treasuryCollections.receipt',
                'clearances' => fn ($query) => $query->oldest('id'),
            ])
            ->withExists('assessments')
            ->firstOrFail();
    }

    private function isEditableDraft(PermitApplication $permitApplication): bool
    {
        return $permitApplication->status === PermitApplicationStatus::Draft
            && $permitApplication->type === PermitApplicationType::New
            && $permitApplication->application_number === null
            && ! $permitApplication->assessments_exists
            && $permitApplication->submittedBy?->business_owner_id !== null
            && $permitApplication->business->business_owner_id === $permitApplication->submittedBy->business_owner_id;
    }

    private function isSubmittableDraft(PermitApplication $permitApplication, ?int $businessOwnerId): bool
    {
        return $permitApplication->status === PermitApplicationStatus::Draft
            && $permitApplication->type === PermitApplicationType::New
            && $permitApplication->application_number === null
            && ! $permitApplication->assessments_exists
            && $businessOwnerId !== null
            && $permitApplication->business->business_owner_id === $businessOwnerId;
    }

    private function isCitizenDocumentUploadAvailable(PermitApplication $permitApplication): bool
    {
        return $permitApplication->status === PermitApplicationStatus::Draft
            && $permitApplication->application_number === null
            && ! $permitApplication->assessments_exists
            && $permitApplication->canContinue();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citizenTimeline(PermitApplication $permitApplication, bool $canViewDocuments, bool $canViewFinancials): array
    {
        return collect($this->buildPermitApplicationTimeline->handle($permitApplication))
            ->filter(fn (array $event): bool => match ($event['category']) {
                'document' => $canViewDocuments,
                'assessment', 'payment', 'treasury' => $canViewFinancials,
                default => true,
            })
            ->map(fn (array $event): array => [
                'key' => $event['key'],
                'category' => $event['category'],
                'title' => $event['title'],
                'description' => $event['description'],
                'status' => $event['status'],
                'occurred_at' => $event['occurred_at'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function draftIntakePayload(PermitApplication $permitApplication): array
    {
        $business = $permitApplication->business;
        $owner = $business->owner;

        return [
            'id' => $permitApplication->id,
            'business_id' => $business->id,
            'draft_version' => $permitApplication->updated_at?->toIso8601String(),
            'owner_name' => $owner->name,
            'owner_email' => $owner->email,
            'owner_phone' => $owner->phone,
            'owner_address' => $owner->address,
            'business_name' => $business->name,
            'trade_name' => $business->trade_name,
            'registration_number' => $business->registration_number,
            'business_address' => $business->address,
            'barangay' => $business->barangay,
            'ownership_type' => $business->ownership_type,
            'organization_name' => $business->organization_name,
            'occupancy' => $business->occupancy,
            'building_name' => $business->building_name,
            'property_index_number' => $business->property_index_number,
            'business_area_square_meters' => $business->business_area_square_meters,
            'male_employee_count' => $business->male_employee_count,
            'female_employee_count' => $business->female_employee_count,
            'business_contact_number' => $business->contact_number,
            'business_email' => $business->email,
            'established_on' => $business->established_on?->toDateString(),
            'started_on' => $business->started_on?->toDateString(),
            'registered_on' => $business->registered_on?->toDateString(),
            'application_year' => $permitApplication->application_year,
            'type' => $permitApplication->type->value,
            'lines' => $permitApplication->lines->map(fn ($line): array => [
                'id' => $line->id,
                'line_of_business_id' => $line->line_of_business_id,
                'declared_gross_sales_pesos' => $this->centsToPesos($line->declared_gross_sales_cents),
                'capital_investment_pesos' => $this->centsToPesos($line->capital_investment_cents),
                'quantity' => $line->quantity,
                'started_on' => $line->started_on?->toDateString(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registryPayload(Request $request): array
    {
        $owner = $request->user()->businessOwner()
            ->with(['businesses' => fn ($query) => $query->orderBy('name')])
            ->first();

        return [
            'linked' => $owner !== null,
            'owner' => $owner === null ? null : [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'address' => $owner->address,
            ],
            'businesses' => $owner?->businesses->map(fn ($business): array => [
                'id' => $business->id,
                'name' => $business->name,
                'trade_name' => $business->trade_name,
                'registration_number' => $business->registration_number,
                'address' => $business->address,
                'barangay' => $business->barangay,
            ])->values() ?? [],
        ];
    }

    private function centsToPesos(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(PermitApplication $permitApplication): array
    {
        return [
            'id' => $permitApplication->id,
            'display_reference' => $permitApplication->application_number
                ?? ($permitApplication->status === PermitApplicationStatus::Draft
                    ? 'Draft #'.$permitApplication->id
                    : 'Application record #'.$permitApplication->id),
            'application_number' => $permitApplication->application_number,
            'type' => $permitApplication->type->value,
            'status' => $permitApplication->status->value,
            'business_name' => $permitApplication->business->name,
            'activity_count' => $permitApplication->lines->count(),
            'saved_at' => $permitApplication->created_at?->toIso8601String(),
        ];
    }
}
