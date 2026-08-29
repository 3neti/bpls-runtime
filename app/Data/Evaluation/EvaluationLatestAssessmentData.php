<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * Traceability from Evaluation to Assessment. `consumes_current_evaluation`
 * is the exact-version proof: an Assessment either was prepared from this
 * exact Evaluation version/fingerprint, or it was not.
 */
class EvaluationLatestAssessmentData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $sequence,
        public readonly int $total_amount_cents,
        public readonly bool $superseded,
        public readonly ?string $decision,
        public readonly ?int $evaluation_version_id,
        public readonly ?string $evaluation_fingerprint,
        public readonly bool $consumes_current_evaluation,
    ) {}
}
