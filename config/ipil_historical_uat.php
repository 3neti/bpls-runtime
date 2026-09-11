<?php

return [
    'enabled' => (bool) env('IPIL_HISTORICAL_UAT_ENABLED', false),
    'environment_id' => env('IPIL_HISTORICAL_UAT_ENVIRONMENT_ID'),
    'reviewer_email' => env('IPIL_HISTORICAL_UAT_REVIEWER_EMAIL'),
];
