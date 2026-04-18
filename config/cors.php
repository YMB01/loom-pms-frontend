<?php

$allowAllOrigins = env('APP_ENV') === 'local'
    || filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

/*
| Build explicit allowed origins for production (APP_DEBUG=false, non-local).
| Set CORS_ALLOWED_ORIGINS (comma-separated) and/or FRONTEND_URL (single origin, e.g. https://app.example.com).
*/
$fromList = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));
$frontend = env('FRONTEND_URL');
if (is_string($frontend) && $frontend !== '') {
    $fromList[] = rtrim($frontend, '/');
}
$productionOrigins = array_values(array_unique(array_filter($fromList)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | In local development (APP_ENV=local or APP_DEBUG=true), all origins are
    | allowed. In production set APP_ENV=production, APP_DEBUG=false, and
    | configure CORS_ALLOWED_ORIGINS and/or FRONTEND_URL for your Next.js app.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowAllOrigins
        ? ['*']
        : $productionOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | When allowed_origins is "*", credentials must be false per CORS spec.
    */
    'supports_credentials' => ! $allowAllOrigins,

];
