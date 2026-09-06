<?php

return [
    'catalog_revision' => 'ipil_application_document_types_v1',
    'requirement_policy_status' => 'unresolved',

    'types' => [
        'dti_registration' => [
            'label' => 'DTI Registration',
            'active' => true,
            'allows_multiple' => false,
        ],
        'sec_registration' => [
            'label' => 'SEC Registration',
            'active' => true,
            'allows_multiple' => false,
        ],
        'bir_registration' => [
            'label' => 'BIR Registration',
            'active' => true,
            'allows_multiple' => false,
        ],
        'barangay_clearance' => [
            'label' => 'Barangay Clearance',
            'active' => true,
            'allows_multiple' => false,
        ],
        'other' => [
            'label' => 'Other',
            'active' => true,
            'allows_multiple' => true,
        ],
    ],
];
