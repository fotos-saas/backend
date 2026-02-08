<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoicing System Configuration
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | Alapértelmezett Beállítások
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'prefix' => 'PS',
        'currency' => 'HUF',
        'language' => 'hu',
        'due_days' => 8,
        'vat_percentage' => 27.00,
        'unit' => 'db',
    ],

    /*
    |--------------------------------------------------------------------------
    | Egység Címkék
    |--------------------------------------------------------------------------
    */

    'unit_labels' => [
        'db' => 'darab',
        'ora' => 'óra',
        'alkalom' => 'alkalom',
        'csomag' => 'csomag',
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Tárolás
    |--------------------------------------------------------------------------
    */

    'pdf_disk' => 'local',
    'pdf_path' => 'invoices/pdf',

];
