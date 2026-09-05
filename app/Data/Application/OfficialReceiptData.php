<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class OfficialReceiptData extends Data
{
    public const Schema = 'bpls.official-receipt-data.v1';

    /**
     * @param  list<array{nature_of_collection: string, account_code: ?string, currency: string, amount_minor: int}>  $collection_rows
     * @param  array{type: string, drawee_bank: ?string, number: ?string, date: ?string}  $payment_instrument
     * @param  array<string, mixed>  $presentation_profile
     * @param  array{view: ?string, pdf: ?string}  $links
     * @param  array{receipt_id: int, treasury_collection_id: int, payment_schedule_id: int, assessment_id: int, assessment_price_report_fingerprint: ?string}  $source
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly int $accountable_form_number,
        public readonly string $form_revision,
        public readonly string $copy_designation,
        public readonly string $receipt_number,
        public readonly ?string $series,
        public readonly string $numbering_authority,
        public readonly bool $synthetic_number,
        public readonly string $issued_on,
        public readonly ?string $agency,
        public readonly ?string $fund,
        public readonly ?string $payor,
        public readonly array $collection_rows,
        public readonly string $currency,
        public readonly int $total_amount_minor,
        public readonly ?string $amount_in_words,
        public readonly array $payment_instrument,
        public readonly ?string $collecting_officer,
        public readonly array $presentation_profile,
        public readonly array $links,
        public readonly array $source,
    ) {}
}
