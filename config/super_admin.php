<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System owner credentials (not stored in the database).
    |--------------------------------------------------------------------------
    */
    'email' => env('SUPER_ADMIN_EMAIL'),
    'password' => env('SUPER_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Session token TTL (hours) — stored in cache.
    |--------------------------------------------------------------------------
    */
    'token_ttl_hours' => (int) env('SUPER_ADMIN_TOKEN_TTL_HOURS', 8),
];
