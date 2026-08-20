<?php

return [
    'actors' => [
        'citizen_applicant' => [
            'email' => env('LIFECYCLE_CITIZEN_EMAIL'),
        ],
        'primary_operator' => [
            'email' => env('LIFECYCLE_OPERATOR_EMAIL', 'test@example.com'),
        ],
        'assessment_preparer' => [
            'email' => env('LIFECYCLE_ASSESSMENT_PREPARER_EMAIL', 'test@example.com'),
        ],
        'assessment_approver' => [
            'email' => env('LIFECYCLE_ASSESSMENT_APPROVER_EMAIL', 'assessment-approver@example.test'),
        ],
        'sample_recipient' => [
            'email' => env('LIFECYCLE_RECIPIENT_EMAIL', 'test@example.com'),
        ],
    ],
];
