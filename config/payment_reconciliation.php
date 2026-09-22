<?php

return [
    'enabled' => (bool) env('PAYMENT_RECONCILIATION_ENABLED', false),
    // Optional inclusive creation cutoff, in app.timezone, formatted Y-m-d H:i:s.
    'starts_at' => env('PAYMENT_RECONCILIATION_STARTS_AT'),
    'connection' => 'payments',
    'queue' => 'payments',
    'interval_seconds' => 300,
    'review_after_hours' => 72,
    'max_checks' => 864,
    'requests_per_minute' => 30,
];
