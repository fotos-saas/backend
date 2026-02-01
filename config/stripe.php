<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    */
    'public_key' => env('STRIPE_PUBLIC_KEY'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => 'huf',

    /*
    |--------------------------------------------------------------------------
    | TablóStúdió Subscription Plans - Stripe Price IDs
    |--------------------------------------------------------------------------
    | These are the Stripe Price IDs for each plan and billing cycle.
    | Create these in Stripe Dashboard: https://dashboard.stripe.com/products
    |
    | For each product, create 2 prices:
    | - Monthly recurring (interval: month)
    | - Yearly recurring (interval: year)
    */
    'prices' => [
        'alap' => [
            'monthly' => env('STRIPE_PRICE_ALAP_MONTHLY', ''),
            'yearly' => env('STRIPE_PRICE_ALAP_YEARLY', ''),
            'paused' => env('STRIPE_PRICE_ALAP_PAUSED', ''),
        ],
        'iskola' => [
            'monthly' => env('STRIPE_PRICE_ISKOLA_MONTHLY', ''),
            'yearly' => env('STRIPE_PRICE_ISKOLA_YEARLY', ''),
            'paused' => env('STRIPE_PRICE_ISKOLA_PAUSED', ''),
        ],
        'studio' => [
            'monthly' => env('STRIPE_PRICE_STUDIO_MONTHLY', ''),
            'yearly' => env('STRIPE_PRICE_STUDIO_YEARLY', ''),
            'paused' => env('STRIPE_PRICE_STUDIO_PAUSED', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Details (for display & limits)
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'alap' => [
            'name' => 'TablóStúdió Alap',
            'description' => 'Kezdő fotósoknak',
            'monthly_price' => 4990,
            'yearly_price' => 49900,
            'paused_price' => 1500,
            'features' => [
                '20 GB tárhely',
                'Max. 3 osztály',
                'Online képválasztás',
                'Sablon szerkesztő',
                'QR kódos megosztás',
                'Email támogatás',
            ],
            'limits' => [
                'storage_gb' => 20,
                'max_classes' => 3,
            ],
        ],
        'iskola' => [
            'name' => 'TablóStúdió Iskola',
            'description' => 'Legtöbb fotósnak ideális',
            'monthly_price' => 14990,
            'yearly_price' => 149900,
            'paused_price' => 2500,
            'popular' => true,
            'features' => [
                '100 GB tárhely',
                'Max. 20 osztály',
                'Saját subdomain',
                'Online fizetés (Stripe)',
                'SMS értesítések',
                'Prioritás támogatás',
            ],
            'limits' => [
                'storage_gb' => 100,
                'max_classes' => 20,
            ],
        ],
        'studio' => [
            'name' => 'TablóStúdió Stúdió',
            'description' => 'Nagyobb stúdióknak',
            'monthly_price' => 29990,
            'yearly_price' => 299900,
            'paused_price' => 9000,
            'features' => [
                '500 GB tárhely',
                'Korlátlan osztály',
                'Custom domain',
                'White-label (saját márka)',
                'API hozzáférés',
                'Dedikált support',
            ],
            'limits' => [
                'storage_gb' => 500,
                'max_classes' => null, // unlimited
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */
    'success_url' => env('FRONTEND_URL', 'https://tablostudio.hu') . '/register-success',
    'cancel_url' => env('FRONTEND_URL', 'https://tablostudio.hu') . '/register-app?cancelled=true',
];
