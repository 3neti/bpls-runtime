<?php

return [
    'name' => env('MUNICIPALITY_NAME', 'Municipality of Ipil'),
    'province' => env('MUNICIPALITY_PROVINCE', 'Zamboanga Sibugay'),
    'system_name' => env('BPLS_SYSTEM_NAME', 'Business Permit and Licensing System'),

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
            'current_runtime_use' => false,
            'legacy_renderer_status' => 'supported',
            'production_layout_status' => 'observed',
        ],
    ],
];
