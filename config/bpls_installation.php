<?php

return [
    'schema_version' => 'bpls.installation.v1',

    'commissioning_administrator' => [
        'email' => env('BPLS_COMMISSIONING_ADMIN_EMAIL'),
        'name' => env('BPLS_COMMISSIONING_ADMIN_NAME', 'BPLS Commissioning Administrator'),
    ],

    'seed_laboratory_actors' => env('BPLS_SEED_LABORATORY_ACTORS', env('APP_ENV') !== 'production'),

    'laboratory_actor_password' => env('BPLS_LABORATORY_ACTOR_PASSWORD', 'password'),
];
