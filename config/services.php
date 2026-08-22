<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'billing' => [
        'provider' => env('BILLING_PROVIDER', 'asaas'),
    ],

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com/v3'),
        'checkout_url' => env('ASAAS_CHECKOUT_URL', 'https://asaas.com/checkoutSession/show'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    'whatsapp' => [
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
        'token' => env('WHATSAPP_WEBHOOK_TOKEN'),
    ],

    'abacatepay' => [
        'api_key' => env('ABACATEPAY_API_KEY'),
        'base_url' => env('ABACATEPAY_BASE_URL', 'https://api.abacatepay.com/v2'),
        'webhook_secret' => env('ABACATEPAY_WEBHOOK_SECRET'),
        'webhook_public_key' => env('ABACATEPAY_WEBHOOK_PUBLIC_KEY', 't9dXRhHHo3yDEj5pVDYz0frf7q6bMKyMRmxxCPIPp3RCplBfXRxqlC6ZpiWmOqj4L63qEaeUOtrCI8P0VMUgo6iIga2ri9ogaHFs0WIIywSMg0q7RmBfybe1E5XJcfC4IW3alNqym0tXoAKkzvfEjZxV6bE0oG2zJrNNYmUCKZyV0KZ3JS8Votf9EAWWYdiDkMkpbMdPggfh1EqHlVkMiTady6jOR3hyzGEHrIz2Ret0xHKMbiqkr9HS1JhNHDX9'),
    ],

];
