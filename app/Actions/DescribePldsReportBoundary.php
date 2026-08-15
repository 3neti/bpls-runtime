<?php

namespace App\Actions;

final class DescribePldsReportBoundary
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
                'key' => 'plds',
                'title' => 'PLDS - Philippine Local Development Survey',
                'official_scope' => 'Legally released business permit applications.',
                'date_basis' => 'Official permit issue date',
                'grain' => 'One row per legally released permit application',
            ],
            'columns' => $this->columns(),
            'blocked_by' => [
                'permit_issuance_authority',
                'permit_release_semantics',
                'official_permit_issue_date',
                'business_category_taxonomy_acceptance',
                'business_subcategory_taxonomy_acceptance',
                'uom_asset_definition_acceptance',
                'production_fee_configuration_reconciliation',
            ],
            'authority_boundary' => [
                'artifact_is_not_issued_permit' => true,
                'released_status_alone_is_not_sufficient' => true,
                'reason' => 'Each PLDS row asserts a legally released permit and official issue date. Neither a permit artifact nor a raw application status proves those facts.',
            ],
            'projection_boundary' => [
                'operational_fields_available' => true,
                'official_rows_available' => false,
                'partial_official_rows_allowed' => false,
                'reason' => 'Registry and declaration fields are available, but publishing them as partial PLDS rows would still imply unsupported permit authority and incomplete classifications.',
            ],
            'legacy_evidence' => [
                'category_columns' => 'The legacy report returned Not Set until category and subcategory data synchronized to its reporting warehouse.',
                'assets' => 'The legacy Assets value was reconstructed from billed UOM-driven line-of-business fees through a second application-database query.',
                'uncollected_fields' => ['social_media_accounts', 'website'],
            ],
            'scope_note' => 'The exact 23-field legacy contract is preserved. No official PLDS row or export is produced until permit authority and PLDS-specific classifications are accepted.',
            'policy_note' => 'Reporting remains downstream of persisted domain evidence and will not recreate assessment logic or infer legal release.',
        ];
    }

    /** @return list<array{position: int, key: string, label: string, source_status: string}> */
    private function columns(): array
    {
        return [
            ['position' => 1, 'key' => 'date_registered_to_lgu', 'label' => 'DATE REGISTERED TO LGU', 'source_status' => 'authority_blocked'],
            ['position' => 2, 'key' => 'business_id_number', 'label' => 'BUSINESS ID NUMBER', 'source_status' => 'registry_available'],
            ['position' => 3, 'key' => 'business_name', 'label' => 'BUSINESS NAME', 'source_status' => 'registry_available'],
            ['position' => 4, 'key' => 'registered_name', 'label' => 'REGISTERED NAME', 'source_status' => 'registry_available'],
            ['position' => 5, 'key' => 'complete_business_address', 'label' => 'COMPLETE BUSINESS ADDRESS', 'source_status' => 'registry_available'],
            ['position' => 6, 'key' => 'tin', 'label' => 'TAX IDENTIFICATION NUMBER', 'source_status' => 'registry_available'],
            ['position' => 7, 'key' => 'new_or_renewal', 'label' => 'NEW OR RENEWAL', 'source_status' => 'lifecycle_available'],
            ['position' => 8, 'key' => 'main_economic_activity', 'label' => 'MAIN ECONOMIC ACTIVITY', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 9, 'key' => 'major_product_services', 'label' => 'MAJOR PRODUCT/ SERVICES', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 10, 'key' => 'gross_revenue', 'label' => 'GROSS REVENUE', 'source_status' => 'declaration_available'],
            ['position' => 11, 'key' => 'capital', 'label' => 'CAPITAL', 'source_status' => 'declaration_available'],
            ['position' => 12, 'key' => 'type_of_business', 'label' => 'TYPE OF BUSINESS', 'source_status' => 'registry_available'],
            ['position' => 13, 'key' => 'total_employees', 'label' => 'TOTAL EMPLOYEES', 'source_status' => 'registry_available'],
            ['position' => 14, 'key' => 'total_employees_male', 'label' => 'TOTAL EMPLOYEES MALE', 'source_status' => 'registry_available'],
            ['position' => 15, 'key' => 'total_employees_female', 'label' => 'TOTAL EMPLOYEES FEMALE', 'source_status' => 'registry_available'],
            ['position' => 16, 'key' => 'telephone_number', 'label' => 'TELEPHONE NUMBER', 'source_status' => 'registry_available'],
            ['position' => 17, 'key' => 'mobile_number', 'label' => 'MOBILE NUMBER', 'source_status' => 'registry_available'],
            ['position' => 18, 'key' => 'email_address', 'label' => 'EMAIL ADDRESS', 'source_status' => 'registry_available'],
            ['position' => 19, 'key' => 'social_media_accounts', 'label' => 'SOCIAL MEDIA ACCOUNTS', 'source_status' => 'not_collected'],
            ['position' => 20, 'key' => 'website', 'label' => 'WEBSITE', 'source_status' => 'not_collected'],
            ['position' => 21, 'key' => 'dti_sec_cda_number', 'label' => 'DTI/SEC/CDA REGISTRATION NUMBER', 'source_status' => 'registry_available'],
            ['position' => 22, 'key' => 'date_registered', 'label' => 'DATE REGISTERED', 'source_status' => 'registry_available'],
            ['position' => 23, 'key' => 'assets', 'label' => 'ASSETS', 'source_status' => 'financial_mapping_unresolved'],
        ];
    }
}
