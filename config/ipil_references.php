<?php

return [
    'barangays' => [
        'schema_version' => 'psgc.ipil-barangays.v1',
        'source' => 'Philippine Statistics Authority PSGC',
        'source_url' => 'https://psa.gov.ph/classification/psgc/barangays/0908305001',
        'verified_on' => '2026-09-06',
        'municipality_psgc_code' => '0908305000',
        'items' => [
            ['code' => '0908305001', 'name' => 'Bacalan'],
            ['code' => '0908305002', 'name' => 'Bangkerohan'],
            ['code' => '0908305003', 'name' => 'Bulu-an'],
            ['code' => '0908305006', 'name' => 'Don Andres'],
            ['code' => '0908305008', 'name' => 'Guituan'],
            ['code' => '0908305009', 'name' => 'Ipil Heights'],
            ['code' => '0908305012', 'name' => 'Labi'],
            ['code' => '0908305013', 'name' => 'Lower Ipil Heights'],
            ['code' => '0908305015', 'name' => 'Lower Taway'],
            ['code' => '0908305016', 'name' => 'Lumbia'],
            ['code' => '0908305018', 'name' => 'Magdaup'],
            ['code' => '0908305022', 'name' => 'Pangi'],
            ['code' => '0908305023', 'name' => 'Poblacion'],
            ['code' => '0908305024', 'name' => 'Sanito'],
            ['code' => '0908305027', 'name' => 'Suclema'],
            ['code' => '0908305029', 'name' => 'Taway'],
            ['code' => '0908305030', 'name' => 'Tenan'],
            ['code' => '0908305032', 'name' => 'Tiayon'],
            ['code' => '0908305034', 'name' => 'Timalang'],
            ['code' => '0908305035', 'name' => 'Tomitom'],
            ['code' => '0908305037', 'name' => 'Upper Pangi'],
            ['code' => '0908305038', 'name' => "Veteran's Village"],
            ['code' => '0908305039', 'name' => 'Makilas'],
            ['code' => '0908305040', 'name' => 'Caparan'],
            ['code' => '0908305041', 'name' => 'Domandan'],
            ['code' => '0908305042', 'name' => 'Doña Josefa'],
            ['code' => '0908305043', 'name' => 'Logan'],
            ['code' => '0908305044', 'name' => 'Maasin'],
        ],
    ],
    'concerned_offices' => [
        'schema_version' => 'ipil.concerned-offices.preview.v1',
        'production_catalog_status' => 'awaiting_nelson_source',
        // Replace this ordered list when Nelson supplies the production routing offices.
        'items' => [
            ['code' => 'engineering', 'label' => 'Municipal Engineering Office', 'fee_rule_codes' => [
                'LAB-NELSON-ENGINEERING-REGULATORY',
            ]],
            ['code' => 'health', 'label' => 'Municipal Health Office', 'fee_rule_codes' => [
                'LAB-NELSON-HEALTH-SANITARY',
                'LAB-NELSON-HEALTH-CERTIFICATE',
            ]],
            ['code' => 'menro', 'label' => 'MENRO', 'fee_rule_codes' => [
                'LAB-NELSON-MENRO-SOLID-WASTE',
            ]],
            ['code' => 'assessor', 'label' => 'Municipal Assessor', 'fee_rule_codes' => [
                'LAB-NELSON-ASSESSOR-WEIGHTS-MEASURES',
            ]],
        ],
    ],
    'nelson_concerned_office_fee_catalog' => [
        'path' => database_path('seeders/data/nelson_concerned_office_fee_catalog.v1.yaml'),
    ],
];
