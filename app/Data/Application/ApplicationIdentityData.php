<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationIdentityData extends Data
{
    public function __construct(
        public readonly int $application_id,
        public readonly ?string $application_number,
        public readonly ?string $tracking_reference,
        public readonly int $application_year,
        public readonly string $type,
        public readonly string $status,
    ) {}
}
