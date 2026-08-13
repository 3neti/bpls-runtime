<?php

return [
    'actors' => [
        'primary_operator' => [
            'email' => env('LIFECYCLE_OPERATOR_EMAIL', 'test@example.com'),
        ],
        'sample_recipient' => [
            'email' => env('LIFECYCLE_RECIPIENT_EMAIL', 'test@example.com'),
        ],
    ],
];
