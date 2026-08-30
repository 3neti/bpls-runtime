<?php

namespace App\Actions;

use App\Assessment\AssessmentSnapshotFingerprint;
use App\Enums\AssessmentStatus;
use App\Enums\TreasuryCounterCheckResult;
use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordBusinessPermitEvaluationCounterCheck
{
    public function __construct(
        private readonly BusinessPermitEvaluationResolver $resolver,
        private readonly AssessmentSnapshotFingerprint $assessmentFingerprint,
    ) {}

    public function handle(
        Assessment $assessment,
        User $treasuryActor,
        TreasuryCounterCheckResult $result = TreasuryCounterCheckResult::NoCorrection,
        ?string $reason = null,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
    ): BusinessPermitEvaluationCounterCheck {
        return DB::transaction(function () use ($assessment, $treasuryActor, $result, $reason, $expectedVersionSequence, $expectedFingerprint): BusinessPermitEvaluationCounterCheck {
            $lockedAssessment = Assessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $lockedAssessment->load(['businessPermitEvaluationVersion.evaluation.currentVersion', 'businessPermitEvaluationVersion.evaluation.permitApplication', 'businessPermitEvaluationVersion.counterCheck', 'decision', 'lines']);
            $version = $lockedAssessment->businessPermitEvaluationVersion;

            if (! $version instanceof BusinessPermitEvaluationVersion) {
                throw new LogicException('Treasury cannot counter-check an Assessment without a source Evaluation version.');
            }

            $lockedEvaluation = $version->evaluation;

            if ($lockedEvaluation->permitApplication->paymentSchedules()->exists()) {
                throw new LogicException('Treasury counter-check belongs before payment scheduling and cannot rewrite payable or paid truth.');
            }

            if ($lockedAssessment->status !== AssessmentStatus::Computed
                || $lockedAssessment->superseded_at !== null
                || $lockedAssessment->decision !== null) {
                throw new LogicException('Treasury can counter-check only the current prepared Assessment before the Municipal Treasurer decision.');
            }

            if ($lockedEvaluation->currentVersion?->id !== $version->id
                || $lockedAssessment->business_permit_evaluation_fingerprint === null
                || ! hash_equals($version->fingerprint, $lockedAssessment->business_permit_evaluation_fingerprint)) {
                throw new LogicException('Treasury cannot counter-check an Assessment that does not bind the current Evaluation version and fingerprint.');
            }

            if ($expectedVersionSequence !== null
                && ($version->sequence !== $expectedVersionSequence
                    || $expectedFingerprint === null
                    || ! hash_equals($version->fingerprint, $expectedFingerprint))) {
                throw new LogicException('The Evaluation changed before Treasury counter-check. Review the latest version; no newer office work was erased.');
            }

            $projection = $this->resolver->resolve($lockedEvaluation, $version);
            if (! $projection['fingerprint_current']) {
                throw new LogicException('Treasury cannot counter-check a stale Evaluation fingerprint. Refresh its dynamic dependencies first.');
            }

            $assessmentSnapshotHash = $this->assessmentFingerprint->hash($lockedAssessment);

            if ($version->counterCheck instanceof BusinessPermitEvaluationCounterCheck) {
                if ($version->counterCheck->assessment_id === $lockedAssessment->id
                    && $version->counterCheck->assessment_snapshot_hash !== null
                    && hash_equals($version->counterCheck->assessment_snapshot_hash, $assessmentSnapshotHash)
                    && $version->counterCheck->result === $result) {
                    return $version->counterCheck;
                }

                throw new LogicException('This Evaluation version already has a Treasury counter-check that is not bound to this exact Assessment snapshot.');
            }

            return $version->counterCheck()->create([
                'assessment_id' => $lockedAssessment->id,
                'assessment_snapshot_hash' => $assessmentSnapshotHash,
                'result' => $result,
                'checked_by_id' => $treasuryActor->id,
                'reason' => $reason,
                'evidence_provenance' => InitializeBusinessPermitEvaluation::EVIDENCE_PROVENANCE,
                'checked_at' => now(),
            ]);
        });
    }
}
