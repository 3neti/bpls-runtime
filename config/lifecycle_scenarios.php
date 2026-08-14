<?php

return [
    'actors' => [
        'citizen_applicant' => [
            'email' => env('LIFECYCLE_CITIZEN_EMAIL'),
        ],
        'primary_operator' => [
            'email' => env('LIFECYCLE_OPERATOR_EMAIL', 'test@example.com'),
        ],
        'sample_recipient' => [
            'email' => env('LIFECYCLE_RECIPIENT_EMAIL', 'test@example.com'),
        ],
    ],
];
