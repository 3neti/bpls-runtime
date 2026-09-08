<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class MunicipalScheduleOfFeesData extends Data
{
    public const Schema = 'bpls.municipal-schedule-of-fees.v1';

    /**
     * @param  list<array<string, mixed>>  $categories
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly string $title,
        public readonly string $scope,
        public readonly string $as_of_date,
        public readonly int $application_year,
        public readonly string $currency,
        public readonly array $categories,
        public readonly array $context = [],
    ) {}
}
