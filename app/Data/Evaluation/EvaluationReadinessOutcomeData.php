<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

class EvaluationReadinessOutcomeData extends Data
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public readonly bool $ready,
        public readonly array $issues,
    ) {}
}
