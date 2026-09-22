<?php

return [
    'enabled' => (bool) env('XCHANGE_PAYMENT_EVENTS_ENABLED', false),
    'secret' => env('XCHANGE_PAYMENT_EVENTS_SECRET'),
    'partner_reference' => env('XCHANGE_PAYMENT_EVENTS_PARTNER_REFERENCE'),
    'queue' => 'payments',
    'lock_store' => env('XCHANGE_PAYMENT_EVENTS_LOCK_STORE', 'database'),
    'timestamp_tolerance_seconds' => 300,
    'review_after_hours' => 24,
];
