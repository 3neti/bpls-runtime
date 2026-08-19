<?php

return [
    'mode' => (bool) env('STAKEHOLDER_PREVIEW_MODE', false),
    'profile' => env('STAKEHOLDER_PREVIEW_PROFILE'),
    'data_classification' => env('STAKEHOLDER_PREVIEW_DATA_CLASSIFICATION'),
    'pii_mode' => env('STAKEHOLDER_PREVIEW_PII_MODE'),
    'production_migration_enabled' => (bool) env('STAKEHOLDER_PREVIEW_PRODUCTION_MIGRATION_ENABLED', true),
    'production_integrations' => env('STAKEHOLDER_PREVIEW_PRODUCTION_INTEGRATIONS'),
    'password' => env('STAKEHOLDER_PREVIEW_PASSWORD'),
    'accounts' => [
        'citizen' => env('STAKEHOLDER_PREVIEW_CITIZEN_EMAIL', 'stakeholder.preview.citizen@example.test'),
        'bplo' => env('STAKEHOLDER_PREVIEW_BPLO_EMAIL', 'stakeholder.preview.bplo@example.test'),
        'treasury' => env('STAKEHOLDER_PREVIEW_TREASURY_EMAIL', 'stakeholder.preview.treasury@example.test'),
        'management' => env('STAKEHOLDER_PREVIEW_MANAGEMENT_EMAIL', 'stakeholder.preview.management@example.test'),
    ],
];
