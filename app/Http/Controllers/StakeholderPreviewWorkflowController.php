<?php

namespace App\Http\Controllers;

use App\Actions\DescribeProvisionalUatPermitCompletion;
use App\Actions\RecordOfficeChargeContribution;
use App\Actions\RecordProvisionalUatPermitDecision;
use App\Enums\PaymentScheduleStatus;
use App\Enums\PermitClearanceStatus;
use App\Enums\StakeholderPreviewPersona;
use App\Http\Requests\RecordProvisionalUatPermitDecisionRequest;
use App\Http\Requests\StoreOfficeChargeContributionRequest;
use App\Models\PermitApplication;
use App\StakeholderPreview\StakeholderPreviewSafety;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StakeholderPreviewWorkflowController extends Controller
{
    public function __construct(private readonly StakeholderPreviewSafety $safety) {}

    public function index(Request $request, DescribeProvisionalUatPermitCompletion $describeCompletion): Response
    {
        $this->safety->ensureReady();
        $persona = $this->safety->personaFor($request->user());

        abort_unless($persona instanceof StakeholderPreviewPersona && ($persona->isConcernedOffice()
            || in_array($persona, [StakeholderPreviewPersona::MayorOffice, StakeholderPreviewPersona::Releasing], true)), 403);

        $officeCode = $persona->officeCode();
        $applicationQuery = PermitApplication::query()
            ->with([
                'business.owner',
                'documents' => fn ($query) => $query->latest('uploaded_at'),
                'lines.lineOfBusiness',
                'officeChargeContributions.submittedBy',
                'assessments.decision',
                'paymentSchedules',
                'clearances',
                'provisionalUatPermitCompletion',
            ])
            ->whereNotNull('submitted_at')
            ->where('metadata->provisional_uat_workflow->semantic_classification', 'provisional_uat');

        if ($officeCode !== null) {
            $applicationQuery->whereHas(
                'officeChargeContributions',
                fn ($query) => $query->where('office_code', $officeCode),
            );
        }

        $applications = $applicationQuery
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (PermitApplication $application) use ($persona, $describeCompletion): array {
                $officeCode = $persona->officeCode();
                $officeContribution = $officeCode === null
                    ? null
                    : $application->officeChargeContributions->firstWhere('office_code', $officeCode);
                $latestAssessment = $application->assessments->sortByDesc('sequence')->first();

                return [
                    'id' => $application->id,
                    'reference' => $application->application_number ?? $application->tracking_reference ?? 'Application #'.$application->id,
                    'type' => $application->type->value,
                    'status' => $application->status->value,
                    'business_name' => $application->business->name,
                    'owner_name' => $application->business->owner->name,
                    'ownership_type' => $application->business->ownership_type,
                    'office_facts' => $this->officeFacts($persona, $application),
                    'documents' => $application->documents->map(fn ($document): array => [
                        'label' => $document->label,
                        'remarks' => $document->remarks,
                    ])->values()->all(),
                    'office_contribution' => $officeContribution === null ? null : [
                        'office_code' => $officeContribution->office_code,
                        'office_label' => $officeContribution->office_label,
                        'is_applicable' => $officeContribution->is_applicable,
                        'status' => $officeContribution->status,
                        'amount_cents' => $officeContribution->amount_cents,
                        'submitted_by' => $officeContribution->submittedBy?->name,
                        'submitted_at' => $officeContribution->submitted_at?->toIso8601String(),
                    ],
                    'office_contributions' => $application->officeChargeContributions->map(fn ($contribution): array => [
                        'office_label' => $contribution->office_label,
                        'is_applicable' => $contribution->is_applicable,
                        'amount_cents' => $contribution->amount_cents,
                        'status' => $contribution->status,
                    ])->values()->all(),
                    'charge_locked' => ($latestAssessment?->decision !== null) || $application->paymentSchedules->isNotEmpty(),
                    'latest_assessment_total_cents' => $latestAssessment?->total_amount_cents,
                    'payment_confirmed' => $application->paymentSchedules->contains(fn ($schedule): bool => $schedule->status === PaymentScheduleStatus::Paid),
                    'clearances_complete' => $application->clearances->isNotEmpty()
                        && $application->clearances->every(fn ($clearance): bool => $clearance->status === PermitClearanceStatus::Completed),
                    'completion' => $describeCompletion->handle($application),
                ];
            });

        return Inertia::render('stakeholder-preview/Workflow', [
            'persona' => [
                'key' => $persona->value,
                'label' => $persona->label(),
                'office_code' => $persona->officeCode(),
            ],
            'office' => $persona->officeCode() === null
                ? null
                : config('stakeholder_preview.weekend_hypothesis.office_charges.'.$persona->officeCode()),
            'applications' => $applications,
        ]);
    }

    /** @return list<array{label: string, value: string}> */
    private function officeFacts(StakeholderPreviewPersona $persona, PermitApplication $application): array
    {
        $business = $application->business;
        $activityNames = $application->lines
            ->pluck('lineOfBusiness.name')
            ->filter()
            ->unique()
            ->implode(', ');
        $employeeCount = $business->male_employee_count === null && $business->female_employee_count === null
            ? null
            : (string) (($business->male_employee_count ?? 0) + ($business->female_employee_count ?? 0));

        $facts = match ($persona) {
            StakeholderPreviewPersona::Engineering => [
                ['label' => 'Business area', 'value' => $business->business_area_square_meters === null ? null : $business->business_area_square_meters.' m²'],
                ['label' => 'Occupancy', 'value' => $business->occupancy],
                ['label' => 'Building', 'value' => $business->building_name],
            ],
            StakeholderPreviewPersona::Mpdo => [
                ['label' => 'Business address', 'value' => $business->address],
                ['label' => 'Barangay', 'value' => $business->barangay],
                ['label' => 'Property index number', 'value' => $business->property_index_number],
                ['label' => 'Declared activities', 'value' => $activityNames],
            ],
            StakeholderPreviewPersona::Assessor => [
                ['label' => 'Ownership type', 'value' => $business->ownership_type],
                ['label' => 'Registration number', 'value' => $business->registration_number],
                ['label' => 'Property index number', 'value' => $business->property_index_number],
            ],
            StakeholderPreviewPersona::Health => [
                ['label' => 'Employees', 'value' => $employeeCount],
                ['label' => 'Business area', 'value' => $business->business_area_square_meters === null ? null : $business->business_area_square_meters.' m²'],
                ['label' => 'Declared activities', 'value' => $activityNames],
            ],
            StakeholderPreviewPersona::Menro => [
                ['label' => 'Declared activities', 'value' => $activityNames],
                ['label' => 'Business area', 'value' => $business->business_area_square_meters === null ? null : $business->business_area_square_meters.' m²'],
                ['label' => 'Occupancy', 'value' => $business->occupancy],
            ],
            default => [],
        };

        return collect($facts)
            ->filter(fn (array $fact): bool => filled($fact['value']))
            ->map(fn (array $fact): array => [
                'label' => $fact['label'],
                'value' => (string) $fact['value'],
            ])
            ->values()
            ->all();
    }

    public function storeOfficeCharge(
        StoreOfficeChargeContributionRequest $request,
        PermitApplication $permitApplication,
        RecordOfficeChargeContribution $recordOfficeCharge,
    ): RedirectResponse {
        try {
            $recordOfficeCharge->handle(
                $permitApplication,
                $request->user(),
                $request->boolean('is_applicable'),
                $request->integer('amount_cents') ?: ($request->validated('amount_cents') === 0 ? 0 : null),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['office_charge' => $exception->getMessage()]);
        }

        return back()->with('status', 'Office review and assessed charge submitted for consolidation.');
    }

    public function recordPermitDecision(
        RecordProvisionalUatPermitDecisionRequest $request,
        PermitApplication $permitApplication,
        RecordProvisionalUatPermitDecision $recordDecision,
    ): RedirectResponse {
        try {
            $recordDecision->handle(
                $permitApplication,
                $request->user(),
                (string) $request->validated('decision'),
                $request->validated('reason'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['permit_decision' => $exception->getMessage()]);
        }

        return back()->with('status', 'Final preview action recorded.');
    }
}
