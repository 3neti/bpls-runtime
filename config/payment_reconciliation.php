<?php

return [
    'enabled' => (bool) env('PAYMENT_RECONCILIATION_ENABLED', false),
    'connection' => 'payments',
    'queue' => 'payments',
    'interval_seconds' => 300,
    'review_after_hours' => 72,
    'max_checks' => 864,
    'requests_per_minute' => 30,
];
