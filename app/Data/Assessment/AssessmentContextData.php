<?php

namespace App\Data\Assessment;

use Spatie\LaravelData\Data;

final class AssessmentContextData extends Data
{
    public function __construct(
        public readonly int $permit_application_id,
        public readonly string $application_type,
        public readonly int $application_year,
        public readonly ?int $evaluation_version_id,
        public readonly ?string $evaluation_fingerprint,
    ) {}
}
