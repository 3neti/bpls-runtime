<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class BusinessPermitData extends Data
{
    public const Schema = 'bpls.business-permit-data.v1';

    /**
     * @param  list<string>  $lines_of_business
     * @param  list<string>  $conditions
     * @param  array{office: string, name: ?string, authority_status: string}  $issuing_authority
     * @param  array{reference: string, status: string, url: string, view_url: string}  $verification
     * @param  list<string>  $blockers
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly string $state,
        public readonly bool $ready,
        public readonly bool $released,
        public readonly bool $valid,
        public readonly ?string $permit_number,
        public readonly ?string $issued_on,
        public readonly ?string $valid_until,
        public readonly string $business_name,
        public readonly string $owner_operator,
        public readonly ?string $business_address,
        public readonly array $lines_of_business,
        public readonly array $conditions,
        public readonly array $issuing_authority,
        public readonly ?string $official_receipt_number,
        public readonly ?string $official_receipt_series,
        public readonly bool $official_receipt_bound,
        public readonly array $verification,
        public readonly ?string $printable_artifact_url,
        public readonly string $statement,
        public readonly array $blockers,
    ) {}
}
