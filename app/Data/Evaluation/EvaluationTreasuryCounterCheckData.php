<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * Treasury's counter-check of one exact Evaluation version. This is
 * investigation/counter-check authority, distinct from Municipal Treasurer
 * Assessment approval — the two must never be visually collapsed.
 */
class EvaluationTreasuryCounterCheckData extends Data
{
    public function __construct(
        public readonly string $checked_at,
        public readonly string $checked_by,
        public readonly ?string $reason,
        public readonly ?string $evidence_provenance,
    ) {}
}
