<?php

return [
    'schema_version' => 'bpls.installation.v1',

    'commissioning_administrator' => [
        'email' => env('BPLS_COMMISSIONING_ADMIN_EMAIL'),
        'name' => env('BPLS_COMMISSIONING_ADMIN_NAME', 'BPLS Commissioning Administrator'),
    ],
];
