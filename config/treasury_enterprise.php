<?php

return [
    // Commission 2026-09-14: workflow UAT hypothesis only, not production policy.
    // Explicit admission is required in addition to the stakeholder-preview
    // safety flags below. This keeps the provisional schedule unavailable in
    // unrelated local environments and avoids hostname-based authority.
    'provisional_uat_enabled' => (bool) env('TREASURY_ENTERPRISE_PROVISIONAL_UAT_ENABLED', false),
    'provisional_uat_context' => env('TREASURY_ENTERPRISE_PROVISIONAL_UAT_CONTEXT'),
    'provisional_uat_allowed_contexts' => ['workflow_uat', 'gate10_local'],
    'workflow_url' => 'https://bpls-stakeholder-preview-uat-uat-5wn03n.laravel.cloud',
    'schedule' => [
        'id' => 'ipil-new-enterprise-provisional-uat',
        'version' => '2026-09-14.v1',
        'policy_status' => 'provisional_uat_pending_municipal_confirmation',
        'authority' => 'Chief Architect UAT commission 2026-09-14: Treasury Enterprise Classification Determination',
        'source' => 'Recovered MRC 3A.02(b) New Business bands; operative 2026 municipal authority pending',
        'currency' => 'PHP',
        'bands' => ['Micro' => 20000, 'Cottage' => 50000, 'Small' => 100000, 'Medium' => 150000, 'Large' => 200000],
    ],
];
