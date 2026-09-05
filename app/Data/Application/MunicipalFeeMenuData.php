<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class MunicipalFeeMenuData extends Data
{
    public const Schema = 'bpls.municipal-fee-menu-data.v1';

    /**
     * @param  list<array<string, mixed>>  $services
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly string $title,
        public readonly string $scope,
        public readonly string $as_of_date,
        public readonly int $application_year,
        public readonly string $currency,
        public readonly string $classification,
        public readonly string $statement,
        public readonly array $services,
    ) {}
}
