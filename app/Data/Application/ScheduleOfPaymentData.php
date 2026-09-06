<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ScheduleOfPaymentData extends Data
{
    /** @param list<array<string, mixed>> $groups */
    public function __construct(
        public readonly string $schema_version,
        public readonly string $currency,
        public readonly array $groups,
        public readonly int $grand_total_minor,
        public readonly int $price_report_total_minor,
        public readonly int $assessment_total_minor,
        public readonly bool $reconciled,
        public readonly ?string $price_report_fingerprint,
    ) {}
}
