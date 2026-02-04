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
    | Storage Addon - Stripe Price IDs
    |--------------------------------------------------------------------------
    | Extra tárhely vásárlás - quantity-based subscription addon.
    |
    | Árak: config/plans.php → storage_addon
    |
    | Stripe beállítás:
    | 1. Stripe Dashboard → Products → Create Product ("Extra tárhely")
    | 2. Add Price (Monthly): 150 HUF, recurring monthly, metered/licensed
    | 3. Add Price (Yearly): 1620 HUF, recurring yearly
    | 4. Price ID-kat .env-be
    */
    'storage_addon' => [
        'price_id_monthly' => env('STRIPE_STORAGE_ADDON_MONTHLY'),
        'price_id_yearly' => env('STRIPE_STORAGE_ADDON_YEARLY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Addons - Stripe Price IDs
    |--------------------------------------------------------------------------
    | Funkció csomagok - Alap előfizetéshez vásárolható kiegészítők.
    |
    | Addon definíciók és árak: config/plans.php → addons
    |
    | Stripe beállítás:
    | 1. Stripe Dashboard → Products → Create Product ("Közösségi csomag")
    | 2. Add Price (Monthly): 1490 HUF, recurring monthly
    | 3. Add Price (Yearly): 14900 HUF, recurring yearly (~17% kedvezmény)
    | 4. Price ID-kat .env-be
    */
    'addons' => [
        'community_pack' => [
            'monthly' => env('STRIPE_ADDON_COMMUNITY_MONTHLY'),
            'yearly' => env('STRIPE_ADDON_COMMUNITY_YEARLY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */
    'success_url' => env('FRONTEND_URL', 'https://tablostudio.hu') . '/register-success',
    'cancel_url' => env('FRONTEND_URL', 'https://tablostudio.hu') . '/register-app?cancelled=true',

    /*
    |--------------------------------------------------------------------------
    | Desktop App (Electron) Deep Link URLs
    |--------------------------------------------------------------------------
    | Desktop alkalmazásból indított fizetések esetén deep link URL-eket
    | használunk a visszairányításhoz, hogy az app megkapja az eredményt.
    |
    | Protocol: photostack://
    */
    'desktop_success_url' => 'photostack://payment/success',
    'desktop_cancel_url' => 'photostack://payment/cancel',
];
