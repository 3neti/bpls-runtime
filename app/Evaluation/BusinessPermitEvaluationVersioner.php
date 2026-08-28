<?php

namespace App\Evaluation;

use App\Enums\PermitApplicationStatus;
use App\Models\BusinessPermitEvaluation;
use App\Models\BusinessPermitEvaluationVersion;
use App\Models\PermitApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class BusinessPermitEvaluationVersioner
{
    public function __construct(private readonly BusinessPermitEvaluationResolver $resolver) {}

    /**
     * @param  callable(BusinessPermitEvaluationVersion): void  $recordChanges
     */
    public function create(
        BusinessPermitEvaluation $evaluation,
        ?User $actor,
        string $reason,
        callable $recordChanges,
        ?int $expectedVersionSequence = null,
        ?string $expectedFingerprint = null,
    ): BusinessPermitEvaluationVersion {
        return DB::transaction(function () use ($evaluation, $actor, $reason, $recordChanges, $expectedVersionSequence, $expectedFingerprint): BusinessPermitEvaluationVersion {
            $lockedEvaluation = BusinessPermitEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            $currentVersion = $lockedEvaluation->versions()->latest('sequence')->first();
            if ($expectedVersionSequence !== null
                && ($currentVersion?->sequence !== $expectedVersionSequence
                    || $expectedFingerprint === null
                    || ! hash_equals($currentVersion->fingerprint, $expectedFingerprint))) {
                throw new LogicException('The Evaluation changed while this work was open. No newer work was erased; review the latest version and try again.');
            }

            $permitApplication = PermitApplication::query()
                ->whereKey($lockedEvaluation->permit_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($permitApplication->paymentSchedules()->exists()) {
                throw new LogicException('The Evaluation cannot change after a Payment Schedule exists. Deficiency, adjustment, refund, and reversal are separate future domains.');
            }

            $version = $lockedEvaluation->versions()->create([
                'sequence' => ($lockedEvaluation->versions()->max('sequence') ?? 0) + 1,
                'fingerprint' => str_repeat('0', 64),
                'reason' => $reason,
                'created_by_id' => $actor?->id,
            ]);

            $recordChanges($version);

            $freshEvaluation = BusinessPermitEvaluation::query()
                ->with(['permitApplication.lines.lineOfBusiness', 'items.revisions.version', 'items.revisions.actor', 'currentVersion.counterCheck'])
                ->findOrFail($lockedEvaluation->id);
            $projection = $this->resolver->resolve($freshEvaluation, $version->fresh());
            $version->forceFill(['fingerprint' => $projection['current_fingerprint']])->save();

            $permitApplication->assessments()
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            if ($permitApplication->status === PermitApplicationStatus::Approval) {
                $permitApplication->forceFill(['status' => PermitApplicationStatus::Assessment])->save();
            }

            return $version->fresh(['revisions', 'counterCheck']);
        });
    }
}
