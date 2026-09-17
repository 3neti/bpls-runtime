<?php

namespace App\Assessment;

use App\Enums\TreasuryCounterCheckResult;
use App\Evaluation\FrozenFinancialEvaluation;
use App\Models\Assessment;
use LogicException;

class AssessmentCounterCheckReadiness
{
    public function __construct(
        private readonly AssessmentSnapshotFingerprint $fingerprint,
        private readonly FrozenFinancialEvaluation $frozen,
    ) {}

    public function state(Assessment $assessment): string
    {
        $required = $assessment->business_permit_evaluation_version_id !== null
            || data_get($assessment->permitApplication->metadata, 'nelson_reconciliation_v1.commissioned_path') === true;
        if (! $required) {
            return 'not_required';
        }
        $version = $assessment->businessPermitEvaluationVersion;
        if ($version === null || $version->evaluation->permit_application_id !== $assessment->permit_application_id
            || $version->fingerprint !== $assessment->business_permit_evaluation_fingerprint) {
            return 'incomplete';
        }
        if (data_get($version->metadata, 'financial_snapshot.schema') !== null) {
            try {
                $this->frozen->assertAssessment($assessment);
            } catch (LogicException) {
                return 'incomplete';
            }
        }
        $check = $assessment->treasuryCounterCheck;
        if ($check === null) {
            return 'awaiting_counter_check';
        }
        if ($check->business_permit_evaluation_version_id !== $version->id
            || $check->assessment_snapshot_hash !== $this->fingerprint->hash($assessment)
            || $check->result !== TreasuryCounterCheckResult::NoCorrection) {
            return 'incomplete';
        }

        return 'checked';
    }
}
