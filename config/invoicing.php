<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoicing System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the invoicing system integration with external
    | providers like Számlázz.hu and Billingo.
    |
    */

    'enabled' => env('INVOICING_ENABLED', false),

    'test_mode' => env('INVOICING_TEST_MODE', true),

    'providers' => [
        'szamlazz_hu' => [
            'base_url' => 'https://www.szamlazz.hu/szamla/',
            'timeout' => 30,
        ],
        'billingo' => [
            'base_url' => 'https://api.billingo.hu/v3',
            'timeout' => 30,
        ],
    ],

];
