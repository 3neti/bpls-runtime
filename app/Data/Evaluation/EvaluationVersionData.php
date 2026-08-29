<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * The exact Evaluation version identity. `sequence` + `fingerprint` are
 * the concurrency token every mutation action must echo back
 * (`expected_version_sequence` / `expected_fingerprint`); the backend
 * remains the sole authority on whether they still match.
 */
class EvaluationVersionData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $sequence,
        public readonly string $fingerprint,
        public readonly bool $fingerprint_current,
        public readonly ?EvaluationTreasuryCounterCheckData $treasury_counter_check,
    ) {}
}
