<?php

namespace App\Data\Assessment;

use Spatie\LaravelData\Data;

final class AssessmentTaxInput extends Data
{
    /** @param array<string, mixed> $provenance */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $currency,
        public readonly int $amount_minor,
        public readonly string $source_version,
        public readonly string $exact_once_key,
        public readonly array $provenance,
    ) {}
}
