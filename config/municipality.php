<?php

return [
    'name' => env('MUNICIPALITY_NAME', 'Municipality of Ipil'),
    'province' => env('MUNICIPALITY_PROVINCE', 'Zamboanga Sibugay'),
    'system_name' => env('BPLS_SYSTEM_NAME', 'Business Permit and Licensing System'),

    'official_receipt' => [
        'profile_key' => env('MUNICIPALITY_RECEIPT_PROFILE', 'ipil-af51-nelson-v1'),
        'profile_version' => 1,
        'layout' => 'af51-nelson-v1',
        'header' => [
            'republic' => 'Republic of the Philippines',
            'province' => env('MUNICIPALITY_RECEIPT_PROVINCE', 'Province of Zamboanga Sibugay'),
            'office' => env('MUNICIPALITY_RECEIPT_OFFICE', 'Office of the Municipal Treasurer'),
            'municipality' => env('MUNICIPALITY_RECEIPT_MUNICIPALITY', 'Municipality of Ipil'),
        ],
        'form' => [
            'accountable_form_number' => 51,
            'revision' => env('MUNICIPALITY_RECEIPT_FORM_REVISION', 'Revised June 2008'),
            'copy_designation' => env('MUNICIPALITY_RECEIPT_COPY', 'ORIGINAL'),
        ],
        'defaults' => [
            'agency' => env('MUNICIPALITY_RECEIPT_AGENCY', 'Municipality of Ipil'),
            'fund' => env('MUNICIPALITY_RECEIPT_FUND', 'General Fund'),
        ],
        'collecting_officer' => [
            'name' => env('MUNICIPALITY_RECEIPT_COLLECTING_OFFICER_NAME', 'MARIA LUZ F. PULMANO'),
            'title' => env('MUNICIPALITY_RECEIPT_COLLECTING_OFFICER_TITLE', 'Municipal Treasurer'),
            'designation' => env('MUNICIPALITY_RECEIPT_COLLECTING_OFFICER_DESIGNATION', 'Collecting Officer'),
            'authority_status' => env('MUNICIPALITY_RECEIPT_COLLECTING_OFFICER_AUTHORITY_STATUS', 'specimen_source_unverified'),
        ],
        'payment_instruments' => [
            ['key' => 'cash', 'label' => 'Cash'],
            ['key' => 'check', 'label' => 'Check'],
            ['key' => 'money_order', 'label' => 'Money Order'],
            ['key' => 'qr_ph', 'label' => 'QR Ph'],
        ],
        'footer_note' => 'Write the number and date of this receipt on the back of a check or money order received.',
        'laboratory_watermark' => 'LABORATORY SPECIMEN — NOT FOR ACCOUNTING USE',
    ],

    'officials' => [
        'municipal_mayor' => [
            'role' => 'Municipal Mayor',
            'name' => env('MUNICIPALITY_MAYOR_NAME', 'Unverified municipal mayor'),
            'title' => env('MUNICIPALITY_MAYOR_TITLE', 'Municipal Mayor'),
            'configured_authority_claim' => env('MUNICIPALITY_MAYOR_AUTHORITY_STATUS', 'unverified'),
            'effective_from' => env('MUNICIPALITY_MAYOR_EFFECTIVE_FROM'),
            'effective_until' => env('MUNICIPALITY_MAYOR_EFFECTIVE_UNTIL'),
            'provenance' => [
                'legacy_fields' => ['mayorName', 'mayorTitle'],
                'legacy_source_status' => 'implemented',
                'production_snapshot_status' => 'observed',
            ],
        ],
        'municipal_treasurer' => [
            'role' => 'Municipal Treasurer',
            'name' => env('MUNICIPALITY_TREASURER_NAME', 'Unverified municipal treasurer'),
            'title' => env('MUNICIPALITY_TREASURER_TITLE', 'Municipal Treasurer'),
            'configured_authority_claim' => env('MUNICIPALITY_TREASURER_AUTHORITY_STATUS', 'unverified'),
            'effective_from' => env('MUNICIPALITY_TREASURER_EFFECTIVE_FROM'),
            'effective_until' => env('MUNICIPALITY_TREASURER_EFFECTIVE_UNTIL'),
            'provenance' => [
                'legacy_fields' => ['treasurerName', 'treasurerTitle'],
                'legacy_source_status' => 'implemented',
                'production_snapshot_status' => 'observed',
            ],
        ],
        'bplo_officer' => [
            'role' => 'BPLO Officer',
            'name' => env('MUNICIPALITY_BPLO_OFFICER_NAME', 'Unverified BPLO officer'),
            'title' => env('MUNICIPALITY_BPLO_OFFICER_TITLE', 'BPLO Officer'),
            'configured_authority_claim' => env('MUNICIPALITY_BPLO_AUTHORITY_STATUS', 'unverified'),
            'effective_from' => env('MUNICIPALITY_BPLO_EFFECTIVE_FROM'),
            'effective_until' => env('MUNICIPALITY_BPLO_EFFECTIVE_UNTIL'),
            'provenance' => [
                'legacy_fields' => [],
                'legacy_source_status' => 'not_found_as_platform_setting',
                'production_snapshot_status' => 'not_observed_as_platform_setting',
            ],
        ],
    ],

    'document_associations' => [
        [
            'official_key' => 'municipal_mayor',
            'document_type' => 'permit_artifact',
            'relationship' => 'configured_signatory',
            'current_runtime_use' => true,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'not_observed',
        ],
        [
            'official_key' => 'bplo_officer',
            'document_type' => 'permit_artifact',
            'relationship' => 'configured_signatory',
            'current_runtime_use' => true,
            'legacy_renderer_status' => 'not_found',
            'production_layout_status' => 'not_observed',
        ],
        [
            'official_key' => 'municipal_treasurer',
            'document_type' => 'permit_template',
            'relationship' => 'template_variable',
            'current_runtime_use' => false,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'not_observed',
        ],
        [
            'official_key' => 'municipal_treasurer',
            'document_type' => 'receipt_template',
            'relationship' => 'template_variable',
            'current_runtime_use' => true,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'observed',
        ],
    ],
];
