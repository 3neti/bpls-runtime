<?php

namespace App\Evaluation;

use App\Assessment\Price\CanonicalFinancialFingerprint;
use App\Models\Assessment;
use App\Models\BusinessPermitEvaluationVersion;
use LogicException;

class FrozenFinancialEvaluation
{
    public const Schema = 'bpls.frozen-financial-evaluation.v1';

    public function __construct(private readonly CanonicalFinancialFingerprint $fingerprint) {}

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        // The digest cannot include its own reference in the Price context.
        data_set($snapshot, 'input.assessment_context.evaluation_fingerprint', null);

        return $this->fingerprint->hash($snapshot);
    }

    /** @return array<string, mixed> */
    public function read(BusinessPermitEvaluationVersion $version): array
    {
        $snapshot = data_get($version->metadata, 'financial_snapshot');
        if (! is_array($snapshot)
            || ($snapshot['schema'] ?? null) !== self::Schema
            || ! hash_equals($version->fingerprint, $this->hash($snapshot))
            || data_get($snapshot, 'input.assessment_context.evaluation_version_id') !== $version->id
            || data_get($snapshot, 'input.assessment_context.evaluation_fingerprint') !== $version->fingerprint
            || data_get($snapshot, 'input.assessment_context.permit_application_id') !== $version->evaluation->permit_application_id
            || ($snapshot['evaluation_id'] ?? null) !== $version->business_permit_evaluation_id) {
            throw new LogicException('Frozen Evaluation identity or financial fingerprint is invalid.');
        }

        return $snapshot;
    }

    public function assertAssessment(Assessment $assessment): void
    {
        $version = $assessment->businessPermitEvaluationVersion;
        if ($version === null || $version->evaluation->permit_application_id !== $assessment->permit_application_id
            || $version->fingerprint !== $assessment->business_permit_evaluation_fingerprint) {
            throw new LogicException('Assessment requires its exact same-Application Evaluation version.');
        }
        $snapshot = $this->read($version);
        if ($assessment->total_amount_cents !== data_get($snapshot, 'report.total.minor')
            || $assessment->currency !== data_get($snapshot, 'report.currency')
            || $this->fingerprint->hash($assessment->assessment_price_input_snapshot ?? []) !== $this->fingerprint->hash($snapshot['input'])
            || $this->fingerprint->hash($assessment->price_report_snapshot ?? []) !== $this->fingerprint->hash($snapshot['report'])
            || $assessment->assessment_price_input_fingerprint !== $this->fingerprint->hash($snapshot['input'])
            || $assessment->price_report_fingerprint !== $this->fingerprint->hash($snapshot['report'])) {
            throw new LogicException('Assessment must freeze the exact Evaluation Price input and result without recalculation.');
        }
    }
}
