<?php

namespace App\Actions;

final class DescribeAnnexCDnfbpReportBoundary
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
                'key' => 'annex_c_dnfbp',
                'title' => 'ANNEX C - DNFBP Semestral Report',
                'official_scope' => 'Designated Non-Financial Businesses and Professions with licenses or permits issued by the municipality.',
                'date_basis' => 'Official permit issue date within an accepted reporting semester',
                'grain' => 'One row per accepted DNFBP-classified business with a legally issued permit in the reporting semester',
                'section_basis' => 'Accepted DNFBP category and sub-category',
            ],
            'columns' => $this->columns(),
            'blocked_by' => [
                'permit_issuance_authority',
                'permit_release_semantics',
                'official_permit_number',
                'official_permit_issue_date',
                'dnfbp_classification_catalog',
                'dnfbp_classification_mapping',
                'reporting_semester_scope',
                'production_classification_reconciliation',
            ],
            'authority_boundary' => [
                'artifact_is_not_issued_permit' => true,
                'released_status_alone_is_not_sufficient' => true,
                'classification_is_regulatory_assertion' => true,
                'report_is_authority_bearing' => true,
                'reason' => 'Each ANNEX C row asserts that the municipality issued a license or permit to a business that qualifies as a DNFBP during the applicable semester. Registry membership, a permit artifact, or a raw application status proves none of those facts.',
            ],
            'projection_boundary' => [
                'operational_fields_available' => true,
                'official_rows_available' => false,
                'partial_official_rows_allowed' => false,
                'reason' => 'Business identity and contact fields are available, but publishing a partial row would still imply unsupported DNFBP classification, permit issuance, and reporting-period eligibility.',
            ],
            'legacy_evidence' => [
                'selection_driven_eligibility' => 'Report users selected ordinary business categories and sub-categories; no accepted DNFBP classification catalog governed inclusion.',
                'unissued_business_inclusion' => 'The legacy query included every non-deleted business in a selected classification even when no permit existed.',
                'latest_permit_selection' => 'The latest non-deleted permit was selected without proving legal issuance or limiting its issue date to a semester.',
                'period_filter' => 'The report was titled semestral but accepted no semester or date parameters.',
            ],
            'scope_note' => 'The exact nine-field flattened legacy export contract is preserved. No official ANNEX C row or export is produced until permit authority, DNFBP eligibility, and semester scope are accepted.',
            'policy_note' => 'Reporting remains downstream of persisted domain evidence and will not infer regulatory classification or legal issuance from ordinary registry records.',
        ];
    }

    /** @return list<array{position: int, key: string, label: string, source_status: string}> */
    private function columns(): array
    {
        return [
            ['position' => 1, 'key' => 'category', 'label' => 'CATEGORY', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 2, 'key' => 'sub_category', 'label' => 'SUB-CATEGORY', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 3, 'key' => 'sequence_number', 'label' => 'NO.', 'source_status' => 'report_sequence_available'],
            ['position' => 4, 'key' => 'business_name', 'label' => 'BUSINESS NAME', 'source_status' => 'registry_available'],
            ['position' => 5, 'key' => 'address', 'label' => 'ADDRESS', 'source_status' => 'registry_available'],
            ['position' => 6, 'key' => 'email', 'label' => 'E-MAIL', 'source_status' => 'registry_available'],
            ['position' => 7, 'key' => 'contact_details', 'label' => 'CONTACT DETAILS', 'source_status' => 'registry_available'],
            ['position' => 8, 'key' => 'permit_number', 'label' => "MAYOR'S PERMIT/LICENSE NO.", 'source_status' => 'authority_blocked'],
            ['position' => 9, 'key' => 'issued_on', 'label' => 'ISSUED ON', 'source_status' => 'authority_blocked'],
        ];
    }
}
