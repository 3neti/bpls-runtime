<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * One step of an Evaluation item's provenance: who asserted what, under
 * which classification, and why. Never a second source of truth — always
 * compiled from `BusinessPermitEvaluationItemRevision` rows.
 */
class EvaluationRevisionData extends Data
{
    public function __construct(
        public readonly int $version_sequence,
        public readonly ?string $action,
        public readonly string $applicability,
        public readonly mixed $value,
        public readonly ?string $source_classification,
        public readonly ?string $actor_name,
        public readonly ?string $reason,
        public readonly ?string $occurred_at,
    ) {}
}
