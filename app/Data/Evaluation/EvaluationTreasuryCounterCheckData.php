<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * Treasury's counter-check of one prepared Assessment and its exact source
 * Evaluation version. This is investigation/counter-check authority, distinct
 * from Municipal Treasurer Assessment approval — the two must never collapse.
 */
class EvaluationTreasuryCounterCheckData extends Data
{
    public function __construct(
        public readonly ?int $assessment_id,
        public readonly ?string $assessment_snapshot_hash,
        public readonly ?string $result,
        public readonly string $checked_at,
        public readonly string $checked_by,
        public readonly ?string $reason,
        public readonly ?string $evidence_provenance,
    ) {}
}
