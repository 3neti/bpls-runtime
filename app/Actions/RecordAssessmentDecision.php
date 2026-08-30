<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentDecisionAction;
use App\Enums\AssessmentStatus;
use App\Enums\PermitApplicationStatus;
use App\Models\Assessment;
use App\Models\AssessmentDecision;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\PermitApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordAssessmentDecision
{
    public function __construct(private readonly AssessmentSnapshotFingerprint $fingerprint) {}

    public function handle(
        Assessment $assessment,
        User $decidedBy,
        AssessmentDecisionAction $action,
        ?string $expectedSnapshotHash = null,
        ?string $reason = null,
    ): AssessmentDecision {
        return DB::transaction(function () use ($assessment, $decidedBy, $action, $expectedSnapshotHash, $reason): AssessmentDecision {
            $lockedAssessment = Assessment::query()
                ->whereKey($assessment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $permitApplication = PermitApplication::query()
                ->whereKey($lockedAssessment->permit_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssessment->load(['decision', 'treasuryCounterCheck', 'lines' => fn ($query) => $query->orderBy('id')]);
            $decidedBy->loadMissing('role');

            $this->assertDecisionMayBeRecorded($lockedAssessment, $permitApplication, $decidedBy);

            $snapshot = $this->fingerprint->snapshot($lockedAssessment);
            $snapshotHash = $this->fingerprint->hash($lockedAssessment);

            if ($expectedSnapshotHash !== null && ! hash_equals($snapshotHash, $expectedSnapshotHash)) {
                throw new DomainException('The assessment changed after this page was opened. Review the current amount before recording a decision.');
            }

            $normalizedReason = filled($reason) ? Str::squish($reason) : null;
            $decidedAt = now();
            $applicationStatusAfterDecision = $action === AssessmentDecisionAction::Approved
                ? PermitApplicationStatus::Approval
                : PermitApplicationStatus::Assessment;

            $decision = $lockedAssessment->decision()->create([
                'decided_by_id' => $decidedBy->id,
                'action' => $action,
                'decided_at' => $decidedAt,
                'reason' => $normalizedReason,
                'assessment_snapshot_hash' => $snapshotHash,
                'total_amount_cents' => $lockedAssessment->total_amount_cents,
                'source_snapshot' => [
                    'assessment_snapshot' => $snapshot,
                    'assessment_snapshot_hash' => $snapshotHash,
                    'decision' => [
                        'action' => $action->value,
                        'actor' => [
                            'user_id' => $decidedBy->id,
                            'name' => $decidedBy->name,
                            'role_code' => $decidedBy->role?->code,
                        ],
                        'decided_at' => $decidedAt->toIso8601String(),
                        'reason' => $normalizedReason,
                        'application_status_before' => $permitApplication->status->value,
                        'application_status_after' => $applicationStatusAfterDecision->value,
                        'authorizes_payment_schedule' => $action === AssessmentDecisionAction::Approved,
                    ],
                ],
            ]);

            if ($action === AssessmentDecisionAction::Approved) {
                $this->markPermitApplicationApproved($permitApplication, $decidedBy, $lockedAssessment, $snapshotHash);
            }

            return $decision->load(['assessment.assessedBy', 'decidedBy']);
        });
    }

    private function assertDecisionMayBeRecorded(
        Assessment $assessment,
        PermitApplication $permitApplication,
        User $decidedBy,
    ): void {
        if ($permitApplication->isHistoricalEvidenceOnly()) {
            throw new DomainException('Historical application evidence cannot receive an operational assessment decision.');
        }

        if ($assessment->status !== AssessmentStatus::Computed || $assessment->superseded_at !== null) {
            throw new DomainException('Only the current computed assessment snapshot may be approved or returned for correction.');
        }

        if ($assessment->assessed_by_id !== null && $assessment->assessed_by_id === $decidedBy->id) {
            throw new DomainException('The Assessment Officer who prepared the assessment cannot record the Municipal Treasurer decision.');
        }

        if ($assessment->decision instanceof AssessmentDecision) {
            throw new DomainException('This assessment snapshot already has an immutable Treasurer decision.');
        }

        if ($assessment->paymentSchedules()->exists()) {
            throw new DomainException('An assessment decision cannot be recorded after payment scheduling has begun.');
        }

        if ($assessment->business_permit_evaluation_version_id !== null) {
            $counterCheck = $assessment->treasuryCounterCheck;
            $snapshotHash = $this->fingerprint->hash($assessment);

            if (! $counterCheck instanceof BusinessPermitEvaluationCounterCheck
                || $counterCheck->business_permit_evaluation_version_id !== $assessment->business_permit_evaluation_version_id
                || $counterCheck->assessment_snapshot_hash === null
                || ! hash_equals($counterCheck->assessment_snapshot_hash, $snapshotHash)) {
                throw new DomainException('The prepared assessment requires Treasury counter-check of this exact snapshot before Municipal Treasurer decision.');
            }
        }

        if ($permitApplication->status !== PermitApplicationStatus::Assessment) {
            throw new DomainException('The permit application is not awaiting a Treasurer assessment decision.');
        }

        if (isset($permitApplication->metadata['terminal_state'])) {
            throw new DomainException('A terminal permit application cannot receive an assessment decision.');
        }
    }

    private function markPermitApplicationApproved(
        PermitApplication $permitApplication,
        User $decidedBy,
        Assessment $assessment,
        string $snapshotHash,
    ): void {
        $metadata = $permitApplication->metadata ?? [];
        $metadata['status_history'] = [
            ...($metadata['status_history'] ?? []),
            [
                'from' => $permitApplication->status->value,
                'to' => PermitApplicationStatus::Approval->value,
                'actor_id' => $decidedBy->id,
                'reason' => 'Municipal Treasurer approved the exact recorded assessment amount for payment.',
                'assessment_id' => $assessment->id,
                'assessment_snapshot_hash' => $snapshotHash,
                'occurred_at' => now()->toIso8601String(),
            ],
        ];

        $permitApplication->forceFill([
            'status' => PermitApplicationStatus::Approval,
            'metadata' => $metadata,
        ])->save();
    }
}
