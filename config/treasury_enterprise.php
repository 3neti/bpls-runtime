<?php

return [
    // Commission 2026-09-14: workflow UAT hypothesis only, not production policy.
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
