<?php

/**
 * Filament Granular Permissions Configuration
 *
 * This config file defines permission structures for Filament resources.
 *
 * With auto_discover enabled:
 * - Resources are automatically detected from App\Filament\Resources
 * - Only use 'resources' for custom configs (tabs, relations, actions)
 * - Use 'resource_overrides' to extend auto-discovered resources
 *
 * Security: New Resources are "closed by default" - permissions exist
 * but no role has access until explicitly granted.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, Resources are automatically discovered from the filesystem.
    | This eliminates the need to manually add each Resource to this config.
    |
    | The 'resources' array is still used for:
    | - Non-Resource pages (dashboard, settings)
    | - Resources with custom tabs/relations/actions
    |
    */
    'auto_discover' => env('FILAMENT_PERMISSIONS_AUTO_DISCOVER', true),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, permission checks will be logged for debugging purposes.
    |
    */
    'debug' => env('PERMISSION_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how long user permissions should be cached.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'key_prefix' => 'filament_permissions_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions
    |--------------------------------------------------------------------------
    |
    | Define all resources and their granular permissions.
    | Structure: resource-key => [label, permissions, tabs, actions, relations]
    |
    */
    'resources' => [
        'dashboard' => [
            'label' => 'Vezérlőpult',
            'namespace' => 'App\\Filament\\Pages\\Dashboard',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'work-sessions' => [
            'label' => 'Munkamenetek',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'tabs' => [
                'basic' => 'Alapadatok',
                'access-methods' => 'Belépési módok',
                'coupon-settings' => 'Kupon beállítások',
                'pricing' => 'Árazás és Csomagok',
                'tablo-mode' => 'Tablófotózás',
            ],
            'actions' => [
                'download-zip' => 'ZIP letöltés',
                'duplicate' => 'Másolás',
            ],
            'relations' => [
                'users' => 'Felhasználók',
                'albums' => 'Albumok',
                'child-sessions' => 'Al-munkamenetek',
            ],
        ],

        'orders' => [
            'label' => 'Rendelések',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'tabs' => [
                'basic' => 'Alapadatok',
                'items' => 'Tételek',
                'payment' => 'Fizetés',
            ],
            'actions' => [
                'generate-invoice' => 'Számla generálás',
                'send-email' => 'Email küldés',
                'refund' => 'Visszatérítés',
            ],
            'relations' => [
                'items' => 'Tételek',
            ],
        ],

        'albums' => [
            'label' => 'Fotózások',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'tabs' => [
                'basic' => 'Alapadatok',
                'statistics' => 'Statisztikák',
                'pricing' => 'Árazás és Csomagok',
            ],
            'relations' => [
                'photos' => 'Képek',
                'users' => 'Felhasználók',
            ],
        ],

        'photos' => [
            'label' => 'Képek',
            'namespace' => 'App\\Filament\\Resources\\PhotoResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'users' => [
            'label' => 'Felhasználók',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'relations' => [
                'photos' => 'Képek',
            ],
        ],

        'admin-users' => [
            'label' => 'Admin Felhasználók',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'roles' => [
            'label' => 'Szerepkörök',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'navigation-managers' => [
            'label' => 'Menü Elrendezés',
            'namespace' => 'App\\Filament\\Resources\\NavigationManagement\\NavigationManagerResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
            ],
        ],

        'permission-management' => [
            'label' => 'Jogosultság Kezelés',
            'namespace' => 'App\\Filament\\Resources\\PermissionManagement\\PermissionManagementResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
            ],
        ],

        'packages' => [
            'label' => 'Csomagok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'relations' => [
                'items' => 'Tételek',
            ],
        ],

        'price-lists' => [
            'label' => 'Árlisták',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'relations' => [
                'prices' => 'Árak',
            ],
        ],

        'coupons' => [
            'label' => 'Kuponok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'shipping-methods' => [
            'label' => 'Szállítási módok',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'payment-methods' => [
            'label' => 'Fizetési módok',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'package-points' => [
            'label' => 'Csomagpontok',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'email-templates' => [
            'label' => 'Email Sablonok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'actions' => [
                'preview' => 'Előnézet',
            ],
        ],

        'email-events' => [
            'label' => 'Email Események',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'email-logs' => [
            'label' => 'Email Naplók',
            'permissions' => [
                'view' => 'Megtekintés',
                'delete' => 'Törlés',
            ],
        ],

        'smtp-accounts' => [
            'label' => 'SMTP Fiókok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'invoicing-providers' => [
            'label' => 'Számlázási Szolgáltatók',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'partner-settings' => [
            'label' => 'Partner Beállítások',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
            ],
        ],

        'stripe-settings' => [
            'label' => 'Stripe Beállítások',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
            ],
        ],

        'queue-management' => [
            'label' => 'Sor Kezelés',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'guest-share-tokens' => [
            'label' => 'Vendég Megosztások',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'print-sizes' => [
            'label' => 'Nyomtatási méretek',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'email-variables' => [
            'label' => 'Email Változók',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'school-classes' => [
            'label' => 'Osztályok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'tablo-projects' => [
            'label' => 'Tablók',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'relations' => [
                'contacts' => 'Kapcsolattartók',
                'persons' => 'Személyek (diákok/tanárok)',
                'notes' => 'Megjegyzések',
                'samples' => 'Minták',
                'emails' => 'Email-ek',
                'polls' => 'Szavazások',
                'guest-sessions' => 'Vendégek',
            ],
        ],

        'tablo-partners' => [
            'label' => 'Tabló Partnerek',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'tablo-statuses' => [
            'label' => 'Tabló Státuszok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'tablo-api-keys' => [
            'label' => 'Tabló API Kulcsok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'tablo-outreaches' => [
            'label' => 'Megkeresések',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'tablo-suggesteds' => [
            'label' => 'Javasolt Tablók',
            'namespace' => 'App\\Filament\\Resources\\TabloSuggestedResource',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'tablo-email-snippets' => [
            'label' => 'Tabló Email Sablonok',
            'namespace' => 'App\\Filament\\Resources\\TabloEmailSnippetResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'tablo-order-analyses' => [
            'label' => 'Megrendelés elemzések',
            'namespace' => 'App\\Filament\\Resources\\TabloOrderAnalysisResource',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'missing-photos' => [
            'label' => 'Hiányzó képek összesítő',
            'namespace' => 'App\\Filament\\Pages\\MissingSummaryPage',
            'permissions' => [
                'view' => 'Megtekintés',
            ],
        ],

        'guest-sessions' => [
            'label' => 'Vendég jóváhagyások',
            'namespace' => 'App\\Filament\\Pages\\PendingVerificationsPage',
            'permissions' => [
                'manage' => 'Kezelés',
            ],
        ],

        'tablo-sample-template-categories' => [
            'label' => 'Minta Kategóriák',
            'namespace' => 'App\\Filament\\Resources\\TabloSampleTemplateCategoryResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'relations' => [
                'templates' => 'Minták',
            ],
        ],

        'tablo-sample-templates' => [
            'label' => 'Minta Táblók',
            'namespace' => 'App\\Filament\\Resources\\TabloSampleTemplateResource',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
        ],

        'settings' => [
            'label' => 'Beállítások',
            'namespace' => 'App\\Filament\\Pages\\SettingsPage',
            'permissions' => [
                'view' => 'Megtekintés',
                'edit' => 'Szerkesztés',
            ],
        ],

        'quotes' => [
            'label' => 'Árajánlatok',
            'permissions' => [
                'view' => 'Megtekintés',
                'create' => 'Létrehozás',
                'edit' => 'Szerkesztés',
                'delete' => 'Törlés',
            ],
            'actions' => [
                'generate-pdf' => 'PDF generálás',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Presets
    |--------------------------------------------------------------------------
    |
    | Predefined permission sets for each role.
    | Use '*' for all permissions or specific permission strings.
    |
    */
    'role_presets' => [
        'super_admin' => [
            '*', // Everything
        ],

        'photo_admin' => [
            'dashboard.view',
            'work-sessions.*',
            'albums.*',
            'users.view',
            'users.edit',
            'orders.*',
            'packages.*',
            'price-lists.*',
            'coupons.*',
            'school-classes.*',
        ],

        'tablo' => [
            'dashboard.view', // Only dashboard
        ],

        'customer' => [
            // No admin access
        ],

        'guest' => [
            // No admin access
        ],
    ],
];

