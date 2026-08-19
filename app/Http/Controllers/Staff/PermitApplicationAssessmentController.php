<?php

namespace App\Http\Controllers\Staff;

use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\RenderAssessmentPdf;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\UserPermission;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\PermitApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermitApplicationAssessmentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $permitApplications = PermitApplication::query()
            ->with([
                'business.owner',
                'lines.lineOfBusiness',
                'assessments' => fn ($query) => $query->with('decision')->latest(),
            ])
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
                'assessment_policy_boundary' => $permitApplication->metadata['assessment_policy_boundary'] ?? null,
            ]);

        return Inertia::render('permit-applications/Assessments/Index', [
            'permitApplications' => $permitApplications,
            'can' => [
                'assess_permit_applications' => auth()->user()?->can(UserPermission::AssessPermitApplications->value) ?? false,
            ],
        ]);
    }

    public function store(PermitApplication $permitApplication, CreateAssessmentForPermitApplication $createAssessment): RedirectResponse
    {
        Gate::authorize(UserPermission::AssessPermitApplications->value);

        try {
            $assessment = $createAssessment->handle($permitApplication, auth()->user());
        } catch (UnsupportedAssessmentPolicy $exception) {
            $metadata = $permitApplication->metadata ?? [];
            $metadata['assessment_policy_boundary'] = [
                'status' => 'blocked',
                'reason' => $exception->getMessage(),
                'blocked_at' => now()->toIso8601String(),
            ];

            $permitApplication->update(['metadata' => $metadata]);

            return back()->withErrors([
                'assessment_policy' => $exception->getMessage(),
            ]);
        }

        return to_route('staff.permit-applications.assessments.show', $assessment);
    }

    public function show(Assessment $assessment, AssessmentSnapshotFingerprint $fingerprint): Response
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $assessment->load([
            'assessedBy',
            'decision.decidedBy.role',
            'permitApplication.business.owner',
            'permitApplication.lines.lineOfBusiness',
            'lines.lineOfBusiness',
            'paymentSchedules' => fn ($query) => $query->latest(),
        ]);

        $latestPaymentSchedule = $assessment->paymentSchedules->first();
        $paymentScheduleAvailable = $assessment->decision?->action === AssessmentDecisionAction::Approved
            && $assessment->decision->total_amount_cents === $assessment->total_amount_cents
            && hash_equals($assessment->decision->assessment_snapshot_hash, $fingerprint->hash($assessment));

        return Inertia::render('permit-applications/Assessments/Show', [
            'assessment' => [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                'assessed_by' => $assessment->assessedBy?->name,
                'total_amount_cents' => $assessment->total_amount_cents,
                'source_snapshot' => $assessment->source_snapshot,
                'payment_schedule_available' => $paymentScheduleAvailable,
                'decision' => $assessment->decision === null ? null : [
                    'id' => $assessment->decision->id,
                    'action' => $assessment->decision->action->value,
                    'decided_at' => $assessment->decision->decided_at->toIso8601String(),
                    'decided_by' => $assessment->decision->decidedBy?->name,
                    'decided_by_role' => $assessment->decision->decidedBy?->role?->name,
                    'reason' => $assessment->decision->reason,
                    'assessment_snapshot_hash' => $assessment->decision->assessment_snapshot_hash,
                    'total_amount_cents' => $assessment->decision->total_amount_cents,
                ],
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
                'latest_payment_schedule' => $latestPaymentSchedule === null ? null : [
                    'id' => $latestPaymentSchedule->id,
                    'sequence' => $latestPaymentSchedule->sequence,
                    'status' => $latestPaymentSchedule->status->value,
                    'payment_mode' => $latestPaymentSchedule->payment_mode,
                    'total_amount_cents' => $latestPaymentSchedule->total_amount_cents,
                    'paid_amount_cents' => $latestPaymentSchedule->paid_amount_cents,
                    'created_at' => $latestPaymentSchedule->created_at?->toIso8601String(),
                ],
            ],
            'can' => [
                'prepare_payment_schedule' => auth()->user()?->can(UserPermission::PreparePaymentSchedules->value) ?? false,
                'approve_assessment' => auth()->user()?->can(UserPermission::ApproveAssessments->value) ?? false,
                'view_payment_schedules' => auth()->user()?->can(UserPermission::ViewPaymentSchedules->value) ?? false,
                'view_assessment_documents' => auth()->user()?->can(UserPermission::ViewPermitApplications->value) ?? false,
            ],
            'assessmentDocumentGaps' => [
                'Generated assessment artifact renders persisted line snapshots only; it does not recalculate fees or taxes.',
                'Full Revenue Code catalog, formula semantics, rounding, PIL, surcharge, and final assessment-sheet layout remain unresolved where not already characterized.',
            ],
        ]);
    }

    public function pdf(Assessment $assessment, RenderAssessmentPdf $renderAssessmentPdf): HttpResponse
    {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        return response($renderAssessmentPdf->handle($assessment))
            ->withHeaders([
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->assessmentFilename($assessment).'"',
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

        $assessment->loadMissing('decision');

        return [
            'id' => $assessment->id,
            'sequence' => $assessment->sequence,
            'status' => $assessment->status->value,
            'total_amount_cents' => $assessment->total_amount_cents,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            'decision' => $assessment->decision === null ? null : [
                'action' => $assessment->decision->action->value,
                'decided_at' => $assessment->decision->decided_at->toIso8601String(),
            ],
        ];
    }

    private function assessmentFilename(Assessment $assessment): string
    {
        $applicationNumber = $assessment->permitApplication()->value('application_number');
        $label = ($applicationNumber ?? 'application-'.$assessment->permit_application_id).'-assessment-'.$assessment->sequence;
        $safeLabel = str($label)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->lower()
            ->toString();

        return ($safeLabel === '' ? 'assessment-'.$assessment->id : $safeLabel).'.pdf';
    }
}
