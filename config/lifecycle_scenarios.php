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
        'preview_engineering' => ['email' => env('LIFECYCLE_PREVIEW_ENGINEERING_EMAIL')],
        'preview_mpdo' => ['email' => env('LIFECYCLE_PREVIEW_MPDO_EMAIL')],
        'preview_assessor' => ['email' => env('LIFECYCLE_PREVIEW_ASSESSOR_EMAIL')],
        'preview_health' => ['email' => env('LIFECYCLE_PREVIEW_HEALTH_EMAIL')],
        'preview_menro' => ['email' => env('LIFECYCLE_PREVIEW_MENRO_EMAIL')],
        'preview_mayor_office' => ['email' => env('LIFECYCLE_PREVIEW_MAYOR_OFFICE_EMAIL')],
        'preview_releasing' => ['email' => env('LIFECYCLE_PREVIEW_RELEASING_EMAIL')],
    ],
];
