<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildComputationAssessmentSlip;
use App\Actions\CreateAssessmentForPermitApplication;
use App\Actions\RenderAssessmentPdf;
use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Enums\UserPermission;
use App\Evaluation\BusinessPermitEvaluationReadiness;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Exceptions\UnsupportedAssessmentPolicy;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentLine;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class PermitApplicationAssessmentController extends Controller
{
    public function index(
        BusinessPermitEvaluationResolver $resolver,
        BusinessPermitEvaluationReadiness $readiness,
    ): Response {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $workSurface = $this->workSurface($user);
        $specializedWorkSurface = $workSurface['id'] !== 'assessment_records';
        $permitApplications = PermitApplication::query()
            ->with([
                'business.owner',
                'lines.lineOfBusiness',
                'assessments' => fn ($query) => $query->with(['decision', 'assessedBy', 'treasuryCounterCheck'])->latest(),
                'paymentSchedules',
                'businessPermitEvaluation.currentVersion.counterCheck',
                'businessPermitEvaluation.items.revisions.version',
                'businessPermitEvaluation.items.revisions.actor',
            ])
            ->latest()
            ->get()
            ->map(function (PermitApplication $permitApplication) use ($readiness, $resolver, $user, $workSurface): array {
                $workItems = $this->workItems($permitApplication, $user, $workSurface['id'], $resolver, $readiness);

                return [
                    ...$this->permitApplicationPayload($permitApplication),
                    'work_items' => $workItems,
                    'can_prepare_assessment' => $workSurface['id'] === 'assessment_preparation'
                        && $workItems !== [],
                ];
            })
            ->when(
                $specializedWorkSurface,
                fn (Collection $applications): Collection => $applications
                    ->filter(fn (array $application): bool => $application['work_items'] !== [])
                    ->values(),
            );
        $workItemCount = $specializedWorkSurface
            ? $permitApplications->sum(fn (array $application): int => count($application['work_items']))
            : $permitApplications->count();
        $permitApplications = $this->paginate($permitApplications);

        return Inertia::render('permit-applications/Assessments/Index', [
            'permitApplications' => $permitApplications,
            'workSurface' => [
                ...$workSurface,
                'count' => $workItemCount,
            ],
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
        } catch (UnsupportedAssessmentPolicy|LogicException $exception) {
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

    public function show(
        Assessment $assessment,
        AssessmentSnapshotFingerprint $fingerprint,
        BuildComputationAssessmentSlip $buildSlip,
    ): Response {
        Gate::authorize(UserPermission::ViewPermitApplications->value);

        $assessment->load([
            'assessedBy',
            'decision.decidedBy.role',
            'permitApplication.business.owner',
            'permitApplication.lines.lineOfBusiness',
            'lines.lineOfBusiness',
            'paymentSchedules' => fn ($query) => $query->latest(),
            'treasuryCounterCheck.checkedBy',
            'businessPermitEvaluationVersion.evaluation.items.revisions.version',
        ]);

        $latestPaymentSchedule = $assessment->paymentSchedules->first();
        $snapshotHash = $fingerprint->hash($assessment);
        $paymentScheduleAvailable = $assessment->decision?->action === AssessmentDecisionAction::Approved
            && $assessment->decision->total_amount_cents === $assessment->total_amount_cents
            && hash_equals($assessment->decision->assessment_snapshot_hash, $snapshotHash);
        $decisionAvailable = $assessment->decision === null
            && $assessment->status === AssessmentStatus::Computed
            && $assessment->superseded_at === null
            && $assessment->paymentSchedules->isEmpty()
            && $assessment->permitApplication->status === PermitApplicationStatus::Assessment
            && $assessment->assessed_by_id !== auth()->id()
            && ($assessment->business_permit_evaluation_version_id === null
                || ($assessment->treasuryCounterCheck?->assessment_snapshot_hash !== null
                    && hash_equals($assessment->treasuryCounterCheck->assessment_snapshot_hash, $snapshotHash)));

        return Inertia::render('permit-applications/Assessments/Show', [
            'computationAssessmentSlip' => $buildSlip->handle($assessment),
            'assessment' => [
                'id' => $assessment->id,
                'sequence' => $assessment->sequence,
                'status' => $assessment->status->value,
                'display_status' => $this->assessmentDisplayStatus($assessment, $latestPaymentSchedule !== null),
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
                'assessed_by' => $assessment->assessedBy?->name,
                'total_amount_cents' => $assessment->total_amount_cents,
                'snapshot_hash' => $snapshotHash,
                'source_snapshot' => $assessment->source_snapshot,
                'business_permit_evaluation' => $assessment->businessPermitEvaluationVersion === null ? null : [
                    'evaluation_id' => $assessment->businessPermitEvaluationVersion->business_permit_evaluation_id,
                    'version_id' => $assessment->businessPermitEvaluationVersion->id,
                    'version_sequence' => $assessment->businessPermitEvaluationVersion->sequence,
                    'fingerprint' => $assessment->business_permit_evaluation_fingerprint,
                    'view_url' => route(
                        'staff.permit-applications.evaluation.show',
                        $assessment->permitApplication,
                        false,
                    ),
                ],
                'treasury_counter_check' => $this->counterCheckPayload(
                    $assessment->treasuryCounterCheck,
                ),
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
                'financial_working_paper' => $this->assessmentWorkingPaper($assessment),
                'lines' => $assessment->lines
                    ->sortBy('code')
                    ->values()
                    ->map(fn (AssessmentLine $line): array => $this->assessmentLinePayload($line)),
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
                'approve_assessment' => $decisionAvailable
                    && (auth()->user()?->can(UserPermission::ApproveAssessments->value) ?? false),
                'view_payment_schedules' => auth()->user()?->can(UserPermission::ViewPaymentSchedules->value) ?? false,
                'view_assessment_documents' => auth()->user()?->can(UserPermission::ViewPermitApplications->value) ?? false,
            ],
            'assessmentDocumentGaps' => [
                'The generated assessment document shows the recorded assessment lines only; it does not recalculate fees or taxes.',
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

    /** @return array<string, mixed> */
    private function permitApplicationPayload(PermitApplication $permitApplication): array
    {
        return [
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
        ];
    }

    /** @return array{id: string, eyebrow: string, title: string, description: string, empty_message: string, count_label: string} */
    private function workSurface(User $user): array
    {
        return match (true) {
            $user->can(UserPermission::ContributeBusinessPermitEvaluations->value) => [
                'id' => 'department_responsibilities',
                'eyebrow' => 'Concerned-office work',
                'title' => 'Your open Evaluation responsibilities',
                'description' => 'Applications appear only while a canonical responsibility belongs to your office or account. Each item explains the Line of Business and why the work arrived.',
                'empty_message' => 'No unresolved Evaluation responsibility is assigned to you.',
                'count_label' => 'new work',
            ],
            $user->can(UserPermission::CounterCheckBusinessPermitEvaluations->value) => [
                'id' => 'treasury_counter_check',
                'eyebrow' => 'Treasury work',
                'title' => 'Evaluations ready for counter-check',
                'description' => 'Only exact current Evaluation versions whose required department work is complete appear here. Counter-check never approves an Assessment.',
                'empty_message' => 'No Evaluation is waiting for Treasury counter-check.',
                'count_label' => 'new work',
            ],
            $user->can(UserPermission::ApproveAssessments->value) => [
                'id' => 'treasurer_approval',
                'eyebrow' => 'Municipal Treasurer work',
                'title' => 'Assessments awaiting your decision',
                'description' => 'Only immutable Assessments prepared by another actor and still awaiting an exact approve-or-return decision appear here.',
                'empty_message' => 'No Assessment is waiting for Municipal Treasurer decision.',
                'count_label' => 'new work',
            ],
            $user->can(UserPermission::AssessPermitApplications->value) => [
                'id' => 'assessment_preparation',
                'eyebrow' => 'Assessment Officer work',
                'title' => 'Applications ready for Assessment',
                'description' => 'Only applications that can legitimately prepare an Assessment appear here. Approved or payable records are suppressed.',
                'empty_message' => 'No application is ready for Assessment preparation.',
                'count_label' => 'new work',
            ],
            default => [
                'id' => 'assessment_records',
                'eyebrow' => 'Assessment records',
                'title' => 'Permit Assessments',
                'description' => 'Review recorded permit applications and their latest Assessment evidence.',
                'empty_message' => 'No permit Assessment records are available.',
                'count_label' => 'records',
            ],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workItems(
        PermitApplication $permitApplication,
        User $user,
        string $surface,
        BusinessPermitEvaluationResolver $resolver,
        BusinessPermitEvaluationReadiness $readiness,
    ): array {
        if ($surface === 'assessment_records') {
            return [];
        }

        if ($surface === 'treasurer_approval') {
            $assessment = $permitApplication->assessments->firstWhere('superseded_at', null);

            if (! $assessment instanceof Assessment
                || $assessment->decision !== null
                || $permitApplication->paymentSchedules->contains('assessment_id', $assessment->id)
                || $assessment->status !== AssessmentStatus::Computed
                || $permitApplication->status !== PermitApplicationStatus::Assessment
                || $assessment->treasuryCounterCheck === null
                || $assessment->assessed_by_id === $user->id) {
                return [];
            }

            return [[
                'label' => "Assessment #{$assessment->sequence}",
                'line_of_business' => $permitApplication->lines->pluck('lineOfBusiness.name')->implode(', '),
                'reason' => 'Prepared by '.($assessment->assessedBy?->name ?? 'recorded Assessment Officer').' for exact Municipal Treasurer approval or return.',
                'responsible_party' => 'municipal_treasurer',
                'resolution' => 'awaiting_decision',
            ]];
        }

        $evaluation = $permitApplication->businessPermitEvaluation;
        if (! $evaluation instanceof BusinessPermitEvaluation) {
            if ($surface === 'assessment_preparation'
                && $permitApplication->assessments->isEmpty()
                && $permitApplication->paymentSchedules->isEmpty()) {
                return [[
                    'label' => 'Prepare Assessment',
                    'line_of_business' => $permitApplication->lines->pluck('lineOfBusiness.name')->implode(', '),
                    'reason' => 'No Business Permit Evaluation exists; the established configured-rule Assessment path remains available.',
                    'responsible_party' => 'assessment_officer',
                    'resolution' => 'ready',
                ]];
            }

            return [];
        }

        $projection = $resolver->resolve($evaluation);
        $items = collect($projection['items']);

        if ($surface === 'department_responsibilities') {
            $lineNames = $permitApplication->lines->pluck('lineOfBusiness.name', 'line_of_business_id');

            return $items
                ->filter(fn (array $item): bool => $item['resolution'] !== 'resolved'
                    && ($item['responsible_party'] === $user->role?->code
                        || data_get($item, 'metadata.authorized_actor_id') === $user->id))
                ->map(fn (array $item): array => [
                    'label' => (string) data_get($item, 'metadata.label', str($item['key'])->headline()),
                    'line_of_business' => $lineNames->get(data_get($item, 'metadata.line_of_business_id')),
                    'reason' => data_get($item, 'metadata.department_selection_reason')
                        ?? $item['reason']
                        ?? 'Required canonical Evaluation responsibility.',
                    'responsible_party' => $item['responsible_party'],
                    'resolution' => $item['resolution'],
                ])
                ->values()
                ->all();
        }

        if ($surface === 'treasury_counter_check') {
            $assessment = $permitApplication->assessments->firstWhere('superseded_at', null);
            $requiredWorkComplete = $items
                ->where('is_required', true)
                ->every(fn (array $item): bool => $item['resolution'] === 'resolved');

            if (! $requiredWorkComplete
                || ! $projection['fingerprint_current']
                || ! $assessment instanceof Assessment
                || $assessment->business_permit_evaluation_version_id !== $projection['version_id']
                || $assessment->business_permit_evaluation_fingerprint !== $projection['current_fingerprint']
                || $evaluation->currentVersion?->counterCheck !== null
                || $permitApplication->paymentSchedules->isNotEmpty()) {
                return [];
            }

            return [[
                'label' => 'Prepared Assessment #'.$assessment->sequence,
                'line_of_business' => $permitApplication->lines->pluck('lineOfBusiness.name')->implode(', '),
                'reason' => 'Treasury may reconcile this immutable Assessment and source Evaluation version '.$projection['version_sequence'].' without approving it.',
                'responsible_party' => 'treasury',
                'resolution' => 'awaiting_counter_check',
            ]];
        }

        if ($permitApplication->assessments->firstWhere('superseded_at', null) instanceof Assessment
            || $permitApplication->paymentSchedules->isNotEmpty()) {
            return [];
        }

        $mode = data_get($permitApplication->metadata, 'business_permit_evaluation.semantic_classification') === 'provisional_uat'
            ? 'provisional_uat'
            : 'commissioned';
        $outcome = $readiness->forAssessment($evaluation, $mode);

        if (! $outcome['ready']) {
            return [];
        }

        return [[
            'label' => 'Prepare immutable Assessment',
            'line_of_business' => $permitApplication->lines->pluck('lineOfBusiness.name')->implode(', '),
            'reason' => 'Every required department responsibility is complete; Assessment preparation precedes Treasury counter-check.',
            'responsible_party' => 'assessment_officer',
            'resolution' => 'ready',
        ]];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function paginate(Collection $items): LengthAwarePaginator
    {
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
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

    private function assessmentDisplayStatus(Assessment $assessment, bool $hasPaymentSchedule): string
    {
        if ($assessment->decision?->action === AssessmentDecisionAction::Approved) {
            return $hasPaymentSchedule ? 'Approved · Payable' : 'Approved for payment';
        }

        if ($assessment->decision?->action === AssessmentDecisionAction::ReturnedForCorrection) {
            return 'Returned for correction';
        }

        return 'Awaiting Municipal Treasurer approval';
    }

    /** @return array<string, mixed>|null */
    private function counterCheckPayload(?BusinessPermitEvaluationCounterCheck $counterCheck): ?array
    {
        if (! $counterCheck instanceof BusinessPermitEvaluationCounterCheck) {
            return null;
        }

        return [
            'assessment_id' => $counterCheck->assessment_id,
            'assessment_snapshot_hash' => $counterCheck->assessment_snapshot_hash,
            'result' => $counterCheck->result?->value,
            'checked_at' => $counterCheck->checked_at->toIso8601String(),
            'checked_by' => $counterCheck->checkedBy->name,
            'reason' => $counterCheck->reason,
            'evidence_provenance' => $counterCheck->evidence_provenance,
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentWorkingPaper(Assessment $assessment): array
    {
        $lineOrder = collect(data_get($assessment->source_snapshot, 'business_permit_evaluation.resolved_line_of_business_ids', []))
            ->merge($assessment->permitApplication->lines->pluck('line_of_business_id'))
            ->merge($assessment->lines->pluck('line_of_business_id')->filter())
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $lineSections = $lineOrder
            ->map(function (int $lineOfBusinessId) use ($assessment): ?array {
                $lines = $assessment->lines
                    ->where('line_of_business_id', $lineOfBusinessId)
                    ->sortBy('code')
                    ->values();

                if ($lines->isEmpty()) {
                    return null;
                }

                return [
                    'line_of_business_id' => $lineOfBusinessId,
                    'line_of_business_name' => $lines->first()?->lineOfBusiness?->name,
                    'charges' => $lines
                        ->map(fn (AssessmentLine $line): array => $this->assessmentLinePayload($line))
                        ->all(),
                    'subtotal_amount_cents' => (int) $lines->sum('amount_cents'),
                ];
            })
            ->filter()
            ->values();

        $applicationCharges = $assessment->lines
            ->whereNull('line_of_business_id')
            ->sortBy('code')
            ->values();
        $groupedTotal = $lineSections->sum('subtotal_amount_cents') + $applicationCharges->sum('amount_cents');

        return [
            'line_sections' => $lineSections->all(),
            'application_charges' => $applicationCharges
                ->map(fn (AssessmentLine $line): array => $this->assessmentLinePayload($line))
                ->all(),
            'application_subtotal_amount_cents' => (int) $applicationCharges->sum('amount_cents'),
            'grand_total_amount_cents' => $assessment->total_amount_cents,
            'grouped_total_amount_cents' => $groupedTotal,
            'reconciles' => $groupedTotal === $assessment->total_amount_cents,
        ];
    }

    /** @return array<string, mixed> */
    private function assessmentLinePayload(AssessmentLine $line): array
    {
        return [
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
        ];
    }
}
