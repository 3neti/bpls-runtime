<?php

namespace App\Actions;

use App\Evaluation\BusinessPermitEvaluationResolver;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationCounterCheck;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordBusinessPermitEvaluationCounterCheck
{
    public function __construct(private readonly BusinessPermitEvaluationResolver $resolver) {}

    public function handle(
        BusinessPermitEvaluation $evaluation,
        User $treasuryActor,
        ?string $reason = null,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
    ): BusinessPermitEvaluationCounterCheck {
        return DB::transaction(function () use ($evaluation, $treasuryActor, $reason, $expectedVersionSequence, $expectedFingerprint): BusinessPermitEvaluationCounterCheck {
            $lockedEvaluation = BusinessPermitEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            $lockedEvaluation->load(['permitApplication', 'currentVersion.counterCheck']);

            if ($lockedEvaluation->permitApplication->paymentSchedules()->exists()) {
                throw new LogicException('Treasury counter-check belongs before payment scheduling and cannot rewrite payable or paid truth.');
            }

            $version = $lockedEvaluation->currentVersion;
            if (! $version instanceof BusinessPermitEvaluationVersion) {
                throw new LogicException('Treasury cannot counter-check an Evaluation without a version.');
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

            return $version->counterCheck()->firstOrCreate(
                [],
                [
                    'checked_by_id' => $treasuryActor->id,
                    'reason' => $reason,
                    'evidence_provenance' => InitializeBusinessPermitEvaluation::EVIDENCE_PROVENANCE,
                    'checked_at' => now(),
                ],
            );
        });
    }
}
