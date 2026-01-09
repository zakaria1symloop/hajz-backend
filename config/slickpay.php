<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SlickPay Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | When set to true, the sandbox API will be used for testing purposes.
    | Set to false in production to use the live API.
    |
    */
    'sandbox' => env('SLICKPAY_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | SlickPay Public Key
    |--------------------------------------------------------------------------
    |
    | Your SlickPay API public key for authentication.
    |
    */
    'public_key' => env('SLICKPAY_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | SlickPay API URLs
    |--------------------------------------------------------------------------
    |
    | The base URLs for SlickPay API endpoints.
    |
    */
    'sandbox_url' => 'https://devapi.slick-pay.com/api/v2',
    'production_url' => 'https://prodapi.slick-pay.com/api/v2',

    /*
    |--------------------------------------------------------------------------
    | Callback URL
    |--------------------------------------------------------------------------
    |
    | The URL that SlickPay will call after payment completion.
    |
    */
    'callback_url' => env('SLICKPAY_CALLBACK_URL', '/api/payments/callback'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URLs
    |--------------------------------------------------------------------------
    |
    | URLs to redirect users after payment completion.
    |
    */
    'success_url' => env('SLICKPAY_SUCCESS_URL', '/reservations/{id}/success'),
    'failure_url' => env('SLICKPAY_FAILURE_URL', '/reservations/{id}/failure'),
];
