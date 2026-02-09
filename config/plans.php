<?php

/**
 * TablóStúdió Előfizetési Csomagok - Központi Konfiguráció
 *
 * SINGLE SOURCE OF TRUTH - Ez a fájl tartalmazza az összes csomag adatot.
 * Frontend: GET /api/plans endpoint-on keresztül érhető el
 * Backend: config('plans.xxx') segítségével használható
 *
 * @see App\Http\Controllers\Api\PlansController
 * @see App\Models\Partner
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Előfizetési Csomagok
    |--------------------------------------------------------------------------
    |
    | A három fő csomag definíciója: alap, iskola, studio
    | Minden csomag tartalmazza:
    | - Árak (havi, éves, szüneteltetett)
    | - Limitek (tárhely, osztályok, iskolák, sablonok)
    | - Feature kulcsok (backend ellenőrzéshez)
    | - Feature címkék (frontend megjelenítéshez)
    |
    */
    'plans' => [
        'alap' => [
            'name' => 'TablóStúdió Alap',
            'description' => 'Kezdő fotósoknak',
            'popular' => false,

            // Árak (Ft)
            'monthly_price' => 4990,
            'yearly_price' => 49900,
            'paused_price' => 1500,

            // Limitek
            'limits' => [
                'storage_gb' => 5,
                'max_classes' => 10,
                'max_schools' => 20,
                'max_contacts' => 50,
                'max_templates' => 10,
            ],

            // AI Help limitek
            'help_limits' => [
                'daily_messages' => 20,
                'daily_tokens' => 50_000,
            ],

            // Feature kulcsok (backend hasFeature() ellenőrzéshez)
            'feature_keys' => [
                'online_selection',
                'templates',
                'qr_sharing',
                'email_support',
                'client_orders',
            ],

            // Feature címkék (frontend megjelenítéshez)
            'feature_labels' => [
                '5 GB tárhely',
                'Max. 10 osztály',
                'Max. 20 iskola',
                'Max. 50 kapcsolattartó',
                'Online képválasztás',
                'Sablon szerkesztő',
                'QR kódos megosztás',
                'Email támogatás',
                'Megrendelés kezelés',
            ],
        ],

        'iskola' => [
            'name' => 'TablóStúdió Iskola',
            'description' => 'Legtöbb fotósnak ideális',
            'popular' => true,

            // Árak (Ft)
            'monthly_price' => 14990,
            'yearly_price' => 149900,
            'paused_price' => 2500,

            // Limitek (null = korlátlan)
            'limits' => [
                'storage_gb' => 100,
                'max_classes' => 20,
                'max_schools' => null,
                'max_contacts' => null,
                'max_templates' => null,
            ],

            // AI Help limitek
            'help_limits' => [
                'daily_messages' => 50,
                'daily_tokens' => 150_000,
            ],

            // Feature kulcsok
            'feature_keys' => [
                'online_selection',
                'templates',
                'qr_sharing',
                'subdomain',
                'branding',
                'stripe_payments',
                'sms_notifications',
                'priority_support',
                'forum',
                'polls',
                'client_orders',
            ],

            // Feature címkék
            'feature_labels' => [
                '100 GB tárhely',
                'Max. 20 osztály',
                'Korlátlan iskola',
                'Saját subdomain',
                'Online fizetés (Stripe)',
                'SMS értesítések',
                'Prioritás támogatás',
                'Fórum',
                'Szavazás',
                'Megrendelés kezelés',
            ],
        ],

        'studio' => [
            'name' => 'TablóStúdió Stúdió',
            'description' => 'Nagyobb stúdióknak',
            'popular' => false,

            // Árak (Ft)
            'monthly_price' => 29990,
            'yearly_price' => 299900,
            'paused_price' => 9000,

            // Limitek (null = korlátlan)
            'limits' => [
                'storage_gb' => 500,
                'max_classes' => null,
                'max_schools' => null,
                'max_contacts' => null,
                'max_templates' => null,
            ],

            // AI Help limitek
            'help_limits' => [
                'daily_messages' => 100,
                'daily_tokens' => 300_000,
            ],

            // Feature kulcsok
            'feature_keys' => [
                'online_selection',
                'templates',
                'qr_sharing',
                'custom_domain',
                'white_label',
                'branding',
                'api_access',
                'dedicated_support',
                'stripe_payments',
                'sms_notifications',
                'forum',
                'polls',
                'client_orders',
            ],

            // Feature címkék
            'feature_labels' => [
                '500 GB tárhely',
                'Korlátlan osztály',
                'Custom domain',
                'White-label (saját márka)',
                'API hozzáférés',
                'Dedikált support',
                'Fórum',
                'Szavazás',
                'Megrendelés kezelés',
            ],
        ],

        'vip' => [
            'name' => 'TablóStúdió VIP',
            'description' => 'Korlátlan tárhely és minden funkció',
            'popular' => false,

            // Árak (Ft)
            'monthly_price' => 49990,
            'yearly_price' => 499900,
            'paused_price' => 15000,

            // Limitek (null = korlátlan)
            'limits' => [
                'storage_gb' => null,      // korlátlan
                'max_classes' => null,
                'max_schools' => null,
                'max_contacts' => null,
                'max_templates' => null,
            ],

            // AI Help limitek
            'help_limits' => [
                'daily_messages' => 200,
                'daily_tokens' => 600_000,
            ],

            // Feature kulcsok
            'feature_keys' => [
                'online_selection',
                'templates',
                'qr_sharing',
                'custom_domain',
                'white_label',
                'branding',
                'api_access',
                'dedicated_support',
                'stripe_payments',
                'sms_notifications',
                'forum',
                'polls',
                'priority_support',
                'unlimited_storage',
                'client_orders',
            ],

            // Feature címkék
            'feature_labels' => [
                'Korlátlan tárhely',
                'Korlátlan osztály',
                'Custom domain',
                'White-label (saját márka)',
                'API hozzáférés',
                'Dedikált support',
                'Prioritás támogatás',
                'Fórum',
                'Szavazás',
                'Megrendelés kezelés',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kiegészítő Csomagok (Addons)
    |--------------------------------------------------------------------------
    |
    | Alap csomaghoz vásárolható kiegészítők.
    | A magasabb szintű csomagok (iskola, studio) már tartalmazzák ezeket.
    |
    */
    'addons' => [
        'community_pack' => [
            'name' => 'Közösségi csomag',
            'description' => 'Fórum + Szavazás funkciók',
            'includes' => ['forum', 'polls'],
            'monthly_price' => 1490,
            'yearly_price' => 14900,
            'available_for' => ['alap'],
        ],
        'branding_pack' => [
            'name' => 'Márkajelzés',
            'description' => 'Saját márkanév, logó és favicon a diákoknak látható oldalakon',
            'includes' => ['branding'],
            'monthly_price' => 0,
            'yearly_price' => 0,
            'free' => true,
            'available_for' => ['alap'],
        ],
        'invoicing_pack' => [
            'name' => 'Számlázás',
            'description' => 'Automatikus számlakiállítás a megrendelésekhez (Számlázz.hu, Billingo)',
            'includes' => ['invoicing'],
            'monthly_price' => 0,
            'yearly_price' => 0,
            'free' => true,
            'available_for' => ['alap'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra Tárhely Addon
    |--------------------------------------------------------------------------
    |
    | GB alapú bővíthető tárhely.
    | Minden csomagnál elérhető.
    |
    */
    'storage_addon' => [
        'unit_price_monthly' => 150,   // Ft/GB/hó
        'unit_price_yearly' => 1620,   // Ft/GB/év (10% kedvezmény)
    ],
];
