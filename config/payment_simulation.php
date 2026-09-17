<?php

return [
    'commissioned' => (bool) env('PAYMENT_SIMULATION_COMMISSIONED', false),
    'context' => env('PAYMENT_SIMULATION_CONTEXT'),
    'allowed_contexts' => ['workflow_uat', 'gate10_local'],
];
