<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

/**
 * A line of business the Municipality currently resolves as part of this
 * application — may differ from the applicant declaration (e.g. Treasury
 * adds an additional activity discovered during counter-check).
 */
class EvaluationResolvedLineData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
    ) {}
}
