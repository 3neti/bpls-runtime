<?php

namespace App\Data\Assessment;

use Spatie\LaravelData\Data;

final class AssessmentPriceModifierInput extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $target_exact_once_key,
        public readonly string $currency,
        public readonly int $amount_minor,
        public readonly string $reason,
        public readonly string $authority,
        public readonly ?int $actor_id,
        public readonly string $office,
        public readonly string $occurred_at,
    ) {}
}
