<?php

namespace App\Data\Assessment;

use Spatie\LaravelData\Data;

final class AssessmentPriceComponentInput extends Data
{
    /** @param array<string, mixed> $explanation */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly string $scope,
        public readonly ?int $permit_application_line_id,
        public readonly ?int $line_of_business_id,
        public readonly ?string $line_of_business_name,
        public readonly ?string $responsible_office,
        public readonly string $currency,
        public readonly int $amount_minor,
        public readonly string $source_type,
        public readonly string $source_identity,
        public readonly string $source_version,
        public readonly string $exact_once_key,
        public readonly ?string $legal_basis,
        public readonly array $explanation,
    ) {}
}
