<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '251'),
    ],

    'africastalking' => [
        'username' => env('AT_USERNAME', env('AFRICASTALKING_USERNAME')),
        'api_key' => env('AT_API_KEY', env('AFRICASTALKING_API_KEY')),
        'from' => env('AT_SENDER_ID', env('AFRICASTALKING_FROM')),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID', env('TWILIO_ACCOUNT_SID')),
        'token' => env('TWILIO_TOKEN', env('TWILIO_AUTH_TOKEN')),
        'from' => env('TWILIO_FROM', env('TWILIO_FROM_NUMBER')),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_basic' => env('STRIPE_PRICE_BASIC'),
        'price_pro' => env('STRIPE_PRICE_PRO'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],

];
