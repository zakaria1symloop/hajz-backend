<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chargily Mode
    |--------------------------------------------------------------------------
    |
    | Set to 'test' for sandbox/testing or 'live' for production.
    |
    */
    'mode' => env('CHARGILY_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Chargily Public Key
    |--------------------------------------------------------------------------
    |
    | Your Chargily API public key.
    |
    */
    'public_key' => env('CHARGILY_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Chargily Secret Key
    |--------------------------------------------------------------------------
    |
    | Your Chargily API secret key.
    |
    */
    'secret_key' => env('CHARGILY_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | The URL that Chargily will call for webhook events.
    |
    */
    'webhook_url' => env('CHARGILY_WEBHOOK_URL', '/api/payments/chargily-webhook'),
];
