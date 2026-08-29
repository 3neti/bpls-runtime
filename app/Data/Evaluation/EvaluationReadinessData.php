<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

class EvaluationReadinessData extends Data
{
    public function __construct(
        public readonly EvaluationReadinessOutcomeData $commissioned,
        public readonly EvaluationReadinessOutcomeData $provisional_uat,
    ) {}
}
