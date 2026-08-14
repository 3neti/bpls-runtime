<?php

return [
    'name' => env('MUNICIPALITY_NAME', 'Municipality of Ipil'),
    'province' => env('MUNICIPALITY_PROVINCE', 'Zamboanga Sibugay'),
    'system_name' => env('BPLS_SYSTEM_NAME', 'Business Permit and Licensing System'),

    'signatories' => [
        'permit' => [
            [
                'role' => 'Municipal Mayor',
                'name' => env('MUNICIPALITY_MAYOR_NAME', 'Unverified municipal mayor'),
                'title' => env('MUNICIPALITY_MAYOR_TITLE', 'Municipal Mayor'),
                'authority_status' => env('MUNICIPALITY_MAYOR_AUTHORITY_STATUS', 'unverified'),
            ],
            [
                'role' => 'BPLO Officer',
                'name' => env('MUNICIPALITY_BPLO_OFFICER_NAME', 'Unverified BPLO officer'),
                'title' => env('MUNICIPALITY_BPLO_OFFICER_TITLE', 'BPLO Officer'),
                'authority_status' => env('MUNICIPALITY_BPLO_AUTHORITY_STATUS', 'unverified'),
            ],
        ],
    ],
];
