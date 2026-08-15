<?php

namespace App\Actions;

final class DescribeCmciLdcsReportBoundary
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'status' => 'blocked',
            'can_generate' => false,
            'can_export' => false,
            'row_count' => 0,
            'rows' => [],
            'report' => [
                'key' => 'cmci_ldcs_annex_b',
                'title' => 'CMCI LDCS Annex B - Local Data Capture Sheet',
                'official_scope' => 'Legally released New and Renewal business permits.',
                'eligible_application_types' => ['New', 'Renewal'],
                'date_basis' => 'Official permit issue date',
                'grain' => 'One row per legally released permit',
            ],
            'municipality_evidence' => [
                'name' => config('municipality.name'),
                'province' => config('municipality.province'),
                'legacy_lgu' => 'IPIL',
                'legacy_region' => 'IX',
                'legacy_classification' => '1st CLASS',
                'legacy_lgu_type' => 'MUNICIPALITY',
                'acceptance_status' => 'unverified_for_official_export',
            ],
            'columns' => $this->columns(),
            'blocked_by' => [
                'permit_issuance_authority',
                'permit_release_semantics',
                'official_permit_number',
                'official_permit_issue_date',
                'official_signatories',
                'qr_verification_semantics',
                'cmci_line_of_business_mapping',
                'cmci_capitalization_size_source',
                'official_lgu_metadata_acceptance',
            ],
            'authority_boundary' => [
                'artifact_is_not_issued_permit' => true,
                'released_status_alone_is_not_sufficient' => true,
                'reason' => 'A generated permit artifact and a raw application status do not prove legal issuance, release, or effect.',
            ],
            'scope_note' => 'The legacy report used permit issue date and official permit number. Those facts do not yet exist under accepted municipal policy in the rescue runtime.',
            'policy_note' => 'CMCI rows and exports remain unavailable until permit authority and the unresolved CMCI mappings are accepted.',
        ];
    }

    /** @return list<array{position: int, key: string, label: string, source_status: string}> */
    private function columns(): array
    {
        return [
            ['position' => 1, 'key' => 'lgu', 'label' => 'LGU', 'source_status' => 'configuration_available'],
            ['position' => 2, 'key' => 'province', 'label' => 'Province', 'source_status' => 'configuration_available'],
            ['position' => 3, 'key' => 'region', 'label' => 'Region', 'source_status' => 'official_configuration_unresolved'],
            ['position' => 4, 'key' => 'classification', 'label' => 'Classification', 'source_status' => 'official_configuration_unresolved'],
            ['position' => 5, 'key' => 'lgu_type', 'label' => 'LGU Type', 'source_status' => 'official_configuration_unresolved'],
            ['position' => 6, 'key' => 'business_name', 'label' => 'Business Name', 'source_status' => 'registry_available'],
            ['position' => 7, 'key' => 'address_building', 'label' => 'Address House/Bldg No.', 'source_status' => 'registry_available'],
            ['position' => 8, 'key' => 'address_street_barangay', 'label' => 'Address Street & Barangay', 'source_status' => 'registry_available'],
            ['position' => 9, 'key' => 'address_subdivision', 'label' => 'Address Subdivision/District', 'source_status' => 'registry_mapping_unresolved'],
            ['position' => 10, 'key' => 'owner_name', 'label' => "Owner's Name", 'source_status' => 'registry_available'],
            ['position' => 11, 'key' => 'line_of_business', 'label' => 'Line of Business', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 12, 'key' => 'business_type', 'label' => 'Business Type', 'source_status' => 'registry_available'],
            ['position' => 13, 'key' => 'capitalization_size', 'label' => 'Capitalization Size', 'source_status' => 'classification_mapping_unresolved'],
            ['position' => 14, 'key' => 'capitalization', 'label' => 'Capitalization', 'source_status' => 'declaration_available'],
            ['position' => 15, 'key' => 'gross_sale', 'label' => 'Gross Sale', 'source_status' => 'declaration_available'],
            ['position' => 16, 'key' => 'application_type', 'label' => 'New/Renewal', 'source_status' => 'lifecycle_available'],
            ['position' => 17, 'key' => 'registration_year', 'label' => 'Year of Registration', 'source_status' => 'registry_available'],
            ['position' => 18, 'key' => 'permit_number', 'label' => 'Permit No.', 'source_status' => 'authority_blocked'],
        ];
    }
}
