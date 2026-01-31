# Tablo szerepkör jogosultságainak javítása

## Probléma
A "tablo" szerepkörű felhasználók látják az összes menüpontot, pedig csak a Vezérlőpultot kellene látniuk.

## Ok
Sok Filament Resource nem használja a `HasGranularPermissions` trait-et, ezért nem ellenőrzik a jogosultságokat.

## Megoldás lépései

### 1. Futtasd le a jogosultság szinkronizálást
```bash
php artisan permissions:assign-defaults
```

Ez beállítja minden szerepkör jogosultságait a `config/filament-permissions.php` alapján.

### 2. Ellenőrizd, hogy létrejöttek-e a jogosultságok
```bash
php artisan permissions:sync
```

### 3. Hozzá kell adni a `HasGranularPermissions` trait-et az alábbi resource-okhoz:

**FIGYELEM:** Minden Resource-nak definiálnia kell a `getPermissionKey()` metódust, hogy helyes permission key-t használjon!

#### Resource-ok amikhez hozzá kell adni a trait-et:

1. **StripeSettingResource.php** - `stripe-settings`
2. **SmtpAccountResource.php** - `smtp-accounts`
3. **EmailEventResource.php** - `email-events`
4. **PartnerSettingResource.php** - `partner-settings`
5. **ShippingMethodResource.php** - `shipping-methods`
6. **PriceListResource.php** - `price-lists`
7. **PackagePointResource.php** - `package-points`
8. **InvoicingProviderResource.php** - `invoicing-providers`
9. **EmailTemplateResource.php** - `email-templates`
10. **PaymentMethodResource.php** - `payment-methods`
11. **CouponResource.php** - `coupons`
12. **PackageResource.php** - `packages`

### 4. Példa resource módosítás:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGranularPermissions;  // Hozzáadás
use Filament\Resources\Resource;

class ExampleResource extends Resource
{
    use HasGranularPermissions;  // Hozzáadás

    // ... többi kód

    // Ha a permission key eltér a slug-golt model névtől, add hozzá:
    protected static function getPermissionKey(): string
    {
        return 'config-key-name'; // pl. 'stripe-settings'
    }
}
```

### 5. Cache törlés
```bash
php artisan optimize:clear
```

## Tesztelés

1. Jelentkezz be "tablo" szerepkörű felhasználóval
2. Csak a "Vezérlőpult" menüpont látszik
3. Más menüpontok nem látszanak

## Config ellenőrzés

A `config/filament-permissions.php` fájlban a tablo szerepkör jogosultságai:

```php
'tablo' => [
    'dashboard.view', // Csak dashboard
],
```
