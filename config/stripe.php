<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    |
    | These are the Stripe API keys for your application. You can find them
    | in your Stripe Dashboard. Make sure to use the correct keys for your
    | environment (test/live).
    |
    */

    'secret_key' => env('STRIPE_SECRET_KEY'),
    'public_key' => env('STRIPE_PUBLIC_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The currency to use for Stripe payments. HUF is the Hungarian Forint.
    |
    */

    'currency' => 'huf',

    /*
    |--------------------------------------------------------------------------
    | Success & Cancel URLs
    |--------------------------------------------------------------------------
    |
    | URLs for redirecting users after checkout completion or cancellation.
    |
    */

    'success_url' => env('FRONTEND_URL', 'http://localhost:4201').'/checkout/success',
    'cancel_url' => env('FRONTEND_URL', 'http://localhost:4201').'/checkout/cancel',
];
