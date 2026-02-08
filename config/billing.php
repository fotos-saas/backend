<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Partner Checkout URLs
    |--------------------------------------------------------------------------
    |
    | A partner Stripe Checkout visszatérési URL-jei.
    | A TABLO_FRONTEND_URL a tablo frontend alkalmazás URL-je.
    |
    */

    'partner_checkout' => [
        'success_url' => env('TABLO_FRONTEND_URL', 'https://kepvalaszto.hu') . '/billing',
        'cancel_url' => env('TABLO_FRONTEND_URL', 'https://kepvalaszto.hu') . '/billing',
    ],

];
