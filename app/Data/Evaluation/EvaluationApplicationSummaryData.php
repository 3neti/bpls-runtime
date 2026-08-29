<?php

namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;

class EvaluationApplicationSummaryData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $application_number,
        public readonly ?string $tracking_reference,
        public readonly string $business_name,
        public readonly string $owner_name,
        public readonly string $type,
        public readonly int $year,
    ) {}
}
