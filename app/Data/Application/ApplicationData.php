<?php

namespace App\Data\Application;

use Spatie\LaravelData\Data;

final class ApplicationData extends Data
{
    public const Schema = 'bpls.application-data.v1';

    /**
     * @param  array<string, mixed>  $applicant
     * @param  array<string, mixed>  $business
     * @param  array<string, mixed>  $routing
     * @param  list<ApplicationOfficeData>  $offices
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $payment
     * @param  list<OfficialReceiptData>  $official_receipts
     * @param  list<ApplicationDocumentData>  $applicant_documents
     * @param  list<SignatureEvidenceData>  $signature_evidence
     * @param  array<string, mixed>  $post_payment
     * @param  list<array<string, mixed>>  $documents
     * @param  list<ApplicationAttachmentData>  $attachments
     * @param  list<array{key: string, label: string}>  $tabs
     */
    public function __construct(
        public readonly string $schema_version,
        public readonly ApplicationIdentityData $identity,
        public readonly array $applicant,
        public readonly array $business,
        public readonly ApplicationDeclarationData $declaration,
        public readonly array $routing,
        public readonly array $offices,
        public readonly array $financial,
        public readonly array $payment,
        public readonly ?ScheduleOfPaymentData $schedule_of_payment,
        public readonly array $official_receipts,
        public readonly array $post_payment,
        public readonly BusinessPermitData $permit,
        public readonly array $documents,
        public readonly array $applicant_documents,
        public readonly array $signature_evidence,
        public readonly MunicipalScheduleOfFeesData $schedule_of_fees,
        public readonly array $attachments,
        public readonly ActorContextData $actor_context,
        public readonly array $tabs,
    ) {}
}
