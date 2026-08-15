<?php

namespace App\Actions;

final class DescribeBspReportBoundary
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'status' => 'blocked',
            'can_generate' => false,
            'can_export' => false,
            'row_count' => 0,
            'rows' => [],
            'report' => [
                'key' => 'bsp_non_bank_entities',
                'title' => 'BSP - Report of Non-Bank Entities Permits',
                'official_scope' => 'Legally permitted pawnshop, money changing, foreign exchange, and remittance operations.',
                'date_basis' => 'Official permit issue date',
                'grain' => 'One row per legally released permit application',
                'default_coverage' => 'Money Service Business',
            ],
            'columns' => $this->columns(),
            'blocked_by' => [
                'permit_issuance_authority',
                'permit_release_semantics',
                'official_permit_number',
                'official_permit_issue_date',
                'bsp_registration_number_source',
                'money_service_business_classification_acceptance',
                'multi_service_line_of_business_representation',
                'failed_to_renew_semantics',
                'cancelled_revoked_retired_semantics',
                'production_classification_reconciliation',
            ],
            'authority_boundary' => [
                'artifact_is_not_issued_permit' => true,
                'released_status_alone_is_not_sufficient' => true,
                'classification_is_regulatory_assertion' => true,
                'reason' => 'Each BSP row asserts both municipal permit authority and a regulated non-bank entity classification. A permit artifact or raw application status proves neither assertion.',
            ],
            'projection_boundary' => [
                'operational_fields_available' => true,
                'official_rows_available' => false,
                'partial_official_rows_allowed' => false,
                'reason' => 'Business identity and address fields are available, but a partial row would still imply unsupported permit issuance, BSP coverage, and line-of-business classification.',
            ],
            'legacy_evidence' => [
                'bsp_registration_number' => 'Always blank because the BPLS did not collect it.',
                'classification' => 'Defaulted to Money Service Business and used one business subcategory for one LOB X-mark.',
                'multi_service_limitation' => 'A business needing multiple X-marks was not representable by the single-subcategory legacy model.',
                'unavailable_statuses' => ['failed_to_renew', 'cancelled_revoked_retired'],
            ],
            'scope_note' => 'The exact 16-field flattened legacy contract is preserved. No official BSP row or export is produced until permit authority and regulated-entity classification are accepted.',
            'policy_note' => 'Reporting remains downstream of persisted domain evidence and will not infer legal release, regulatory registration, or adverse permit status.',
        ];
    }

    /** @return list<array{position: int, key: string, label: string, source_status: string}> */
    private function columns(): array
    {
        return [
            ['position' => 1, 'key' => 'no', 'label' => 'NO.', 'source_status' => 'report_sequence_available'],
            ['position' => 2, 'key' => 'bsp_registration_number', 'label' => 'BSP REGISTRATION NO.', 'source_status' => 'not_collected'],
            ['position' => 3, 'key' => 'entity_name', 'label' => 'NAME OF ENTITY', 'source_status' => 'registry_available'],
            ['position' => 4, 'key' => 'address_house_building_number', 'label' => 'ADDRESS - HOUSE/BLDG. NO.', 'source_status' => 'registry_available'],
            ['position' => 5, 'key' => 'address_street', 'label' => 'ADDRESS - STREET', 'source_status' => 'registry_available'],
            ['position' => 6, 'key' => 'address_barangay', 'label' => 'ADDRESS - BARANGAY', 'source_status' => 'registry_available'],
            ['position' => 7, 'key' => 'lob_pawnshop', 'label' => 'LOB - PAWNSHOP', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 8, 'key' => 'lob_money_changer', 'label' => 'LOB - MONEY CHANGER/FOREIGN EXCHANGE DEALER', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 9, 'key' => 'lob_rtc', 'label' => 'LOB - REMITTANCE AND TRANSFER COMPANY (RTC)', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 10, 'key' => 'lob_rtc_virtual_currency', 'label' => 'LOB - RTC WITH VIRTUAL CURRENCY EXCHANGE', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 11, 'key' => 'permit_date_of_issuance', 'label' => 'BUSINESS PERMIT - DATE OF ISSUANCE', 'source_status' => 'authority_blocked'],
            ['position' => 12, 'key' => 'permit_number', 'label' => 'BUSINESS PERMIT - NUMBER', 'source_status' => 'authority_blocked'],
            ['position' => 13, 'key' => 'status_new', 'label' => 'STATUS - NEW', 'source_status' => 'application_type_available'],
            ['position' => 14, 'key' => 'status_renewal', 'label' => 'STATUS - RENEWAL', 'source_status' => 'application_type_available'],
            ['position' => 15, 'key' => 'status_failed_to_renew', 'label' => 'STATUS - FAILED TO RENEW', 'source_status' => 'status_semantics_unavailable'],
            ['position' => 16, 'key' => 'status_cancelled_revoked_retired', 'label' => 'STATUS - CANCELLED/REVOKED/RETIRED', 'source_status' => 'status_semantics_unavailable'],
        ];
    }
}
