# Role Configuration Import/Export Feature

## Áttekintés

A Filament jogosultság- és menükezelő rendszerbe integrált import/export funkcionalitás lehetővé teszi, hogy:
- **Exportáljunk** szerepkör-specifikus konfigurációkat JSON formátumban
- **Importáljunk** konfigurációkat JSON fájlból
- **Backup-oljunk** és **migrált** beállításokat különböző környezetek között

## Funkciók

### 1. Export Funkció

**Mire jó?**
- Mentés és backup készítése egy szerepkör teljes konfigurációjáról
- Átvitel szerepkör-beállításokról különböző környezetek között (dev → staging → production)
- Verziókezelés és változások követése

**Mit exportál?**
- ✅ Összes permission (jogosultság) amit a szerepkör kap
- ✅ Navigation items (menüpontok) szerepkör-specifikus beállításai
- ✅ Navigation groups (menücsoportok) szerepkör-specifikus beállításai

**Használat:**
1. Válassz ki egy szerepkört a Jogosultság Kezelés oldalon
2. Kattints az **Export** gombra a fejlécben
3. A böngésző letölti a `role_config_{role_name}_{timestamp}.json` fájlt

### 2. Import Funkció

**Mire jó?**
- Korábban exportált konfiguráció visszatöltése
- Gyors beállítás más környezetben
- Szerepkör klónozása/másolása

**Mit importál?**
- ✅ Permissions (jogosultságok)
- ✅ Navigation items (menüpontok beállításai)
- ✅ Navigation groups (menücsoportok)

**Használat:**
1. Válassz ki egy szerepkört
2. Kattints az **Import** gombra
3. Töltsd fel a JSON fájlt
4. Válaszd ki az importálás módját:
   - **Replace (Csere)**: Meglévő beállítások törlése és felülírása
   - **Merge (Egyesítés)**: Meglévő beállítások megtartása, új elemek hozzáadása
5. Kattints az **Importálás** gombra

### 3. Merge vs Replace Mode

#### Replace Mode (Alapértelmezett)
- **Töröl**: Minden meglévő permission-t, navigation item-et és group-ot a role-nál
- **Hozzáad**: Csak az importált JSON-ben lévő elemeket
- **Használd**: Ha teljesen új konfigurációt akarsz, vagy biztosan felül akarod írni az összes beállítást

#### Merge Mode
- **Megtart**: Minden meglévő beállítást
- **Hozzáad**: Az importált JSON-ben lévő új elemeket
- **Frissít**: Ha egy elem már létezik, frissíti az értékét
- **Használd**: Ha csak új elemeket akarsz hozzáadni vagy meglévőket frissíteni anélkül, hogy törlődjön bármi

## JSON Struktúra

```json
{
  "role": "photo_admin",
  "exported_at": "2025-10-19T10:30:00+00:00",
  "permissions": [
    "dashboard.view",
    "work-sessions.view",
    "work-sessions.create",
    "work-sessions.edit",
    "albums.view",
    "albums.create"
  ],
  "navigation": {
    "items": [
      {
        "resource_key": "dashboard",
        "label": "Vezérlőpult",
        "navigation_group": null,
        "sort_order": -1,
        "is_visible": true
      },
      {
        "resource_key": "work-sessions",
        "label": "Munkamenetek",
        "navigation_group": "main",
        "sort_order": 0,
        "is_visible": true
      }
    ],
    "groups": [
      {
        "key": "main",
        "label": "Fő menü",
        "sort_order": 0,
        "collapsed": false,
        "is_system": false
      }
    ]
  }
}
```

### Mezők magyarázata

#### Root level
- `role`: A szerepkör neve (információs jellegű)
- `exported_at`: Export időpontja ISO 8601 formátumban
- `permissions`: Jogosultságok listája
- `navigation`: Navigációs beállítások

#### Navigation Items
- `resource_key`: A resource egyedi azonosítója (pl. "work-sessions")
- `label`: Megjelenített címke a menüben (opcionális)
- `navigation_group`: Melyik csoportba tartozik (opcionális, null = nincs csoport)
- `sort_order`: Sorrend a menüben (alacsonyabb = előrébb)
- `is_visible`: Látható-e a menüben

#### Navigation Groups
- `key`: Csoport egyedi azonosítója
- `label`: Megjelenített név
- `sort_order`: Csoportok sorrendje
- `collapsed`: Alapból összecsukva van-e
- `is_system`: Rendszer csoport-e (védett a törlés ellen)

## Validáció és Hibakezelés

Az import folyamat során a rendszer validálja:

✅ **JSON formátum helyessége**
- Érvényes JSON szintaxis
- Megfelelő struktúra

✅ **Permissions ellenőrzése**
- Csak létező permissions importálhatók
- Nem létező permission esetén warning, de folytatódik az import

✅ **Resource keys ellenőrzése**
- Resource key-ek megléte
- Helyes formátum

✅ **Rendszer védelem**
- System groups védve vannak a felülírás ellen
- Kritikus beállítások megőrzése

## Használati példák

### 1. Környezetek közötti migráció

**Scenario**: Development környezetben beállítottad a "photographer" szerepkört, és átakarod vinni production-be.

```bash
# 1. Dev környezetben: Export
- Lépj be a Jogosultság Kezelésbe
- Válaszd ki a "photographer" szerepkört
- Kattints Export
- Mentsd el: role_config_photographer_2025-10-19.json

# 2. Production környezetben: Import
- Lépj be a Jogosultság Kezelésbe
- Válaszd ki a "photographer" szerepkört (vagy hozz létre új-at)
- Kattints Import
- Válaszd ki a fájlt
- Kapcsold ki a Merge módot (Replace)
- Importálj
```

### 2. Role backup és visszaállítás

**Scenario**: Mielőtt nagyobb változtatásokat végzel, mentést készítesz.

```bash
# Backup készítése
1. Export a jelenlegi konfigurációt
2. Mentsd el biztonságos helyre

# Ha valami elromlik, visszaállítás
1. Import a mentett fájlt
2. Replace mode használata
```

### 3. Role klónozás

**Scenario**: Van egy "admin" role és szeretnél egy "junior_admin" role-t hasonló jogokkal.

```bash
# 1. Export az admin role-t
- Válaszd ki "admin" role-t
- Export

# 2. Hozz létre "junior_admin" role-t a rendszerben

# 3. Import az admin konfigurációt
- Válaszd ki "junior_admin" role-t
- Import az admin JSON-t
- Merge vagy Replace mode

# 4. Fine-tune: Vegyél el pár jogot manuálisan
```

## Biztonsági megfontolások

⚠️ **Fontos tudnivalók:**

1. **Permissions validáció**: Csak létező permissions kerülnek importálásra
2. **System groups védelem**: Rendszer csoportok nem írhatók felül
3. **Transaction védelem**: Ha hiba van az import során, minden visszagördül (rollback)
4. **Fájl méret limit**: Maximum 2MB JSON fájl

## Troubleshooting

### "Érvénytelen JSON formátum" hiba
- **Ok**: A fájl nem valid JSON
- **Megoldás**: Ellenőrizd a fájlt JSON validátorral (pl. jsonlint.com)

### "Jogosultság nem létezik" warning
- **Ok**: Az importált permission nem található az adatbázisban
- **Megoldás**: Ez nem kritikus hiba, az import folytatódik a többi permission-nel

### "Rendszer csoport nem módosítható" hiba
- **Ok**: Olyan navigation group-ot próbálsz módosítani ami védett
- **Megoldás**: Ne módosítsd a system groups-okat a JSON-ben

### Import után nem látszanak a változások
- **Ok**: Cache probléma
- **Megoldás**: A rendszer automatikusan törli a cache-t, de ha mégis probléma van:
  ```bash
  php artisan cache:clear
  php artisan view:clear
  ```

## Programozói felület (API)

Ha programozottan szeretnéd használni:

```php
use App\Services\RoleConfigurationService;
use Spatie\Permission\Models\Role;

$service = app(RoleConfigurationService::class);
$role = Role::findByName('photo_admin');

// Export
$config = $service->exportRoleConfiguration($role);
$json = $service->exportRoleConfigurationAsJson($role, prettyPrint: true);

// Import
$result = $service->importRoleConfiguration($role, $config, mergeMode: false);
// vagy
$result = $service->importRoleConfigurationFromJson($role, $json, mergeMode: true);

// Eredmény ellenőrzése
if ($result['success']) {
    // Sikeres import
    foreach ($result['messages'] as $message) {
        echo $message . "\n";
    }
} else {
    // Hiba történt
    foreach ($result['errors'] as $error) {
        echo "ERROR: " . $error . "\n";
    }
}
```

## Fájlok

Az implementáció fájljai:

- **Service**: `backend/app/Services/RoleConfigurationService.php`
- **Component**: `backend/app/Livewire/PermissionManager.php`
- **View**: `backend/resources/views/livewire/permission-manager.blade.php`

## Verziókezelés

Az exportált JSON fájlok tartalmaznak egy `exported_at` mezőt, amely az export időpontját tárolja. Ez hasznos:
- Több verzió között választáskor
- Audit trail (változások követése)
- Konfliktusmegoldáskor

## Következő lépések / Jövőbeli fejlesztések

- 🔄 Bulk export/import (több role egyszerre)
- 📊 Diff view (különbségek megjelenítése két konfiguráció között)
- 🕒 Automatikus backup készítés változtatások előtt
- 🔍 Import preview (előnézet importálás előtt)
- 📝 Change log generálás
