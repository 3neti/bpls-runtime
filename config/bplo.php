<?php

return [
    'routing_sentinel' => [
        'enabled' => (bool) env('BPLS_ROUTING_SENTINEL_ENABLED', env('STAKEHOLDER_PREVIEW_MODE', false)),
        'review_minutes' => (int) env('BPLS_ROUTING_REVIEW_MINUTES', 15),
        'clock' => env('BPLS_ROUTING_CLOCK', 'elapsed'),
        'profile_path' => database_path('seeders/data/ipil_bplo_routing_profiles.yaml'),
        'assessment_profile_path' => database_path('seeders/data/ipil_laboratory_assessment_profiles.yaml'),
    ],
];
