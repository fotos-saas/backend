# Work Session Custom User ZIP Download Feature

## Overview

Ez a funkció lehetővé teszi az adminisztrátorok számára, hogy egyedi kiválasztással töltsék le a munkamenet felhasználóinak képeit. Minden felhasználóhoz külön választható, hogy:
- **Összes kép** (retus + tabló)
- **Csak retus képek**
- **Csak tabló kép**

## Feature Location

**Filament Admin Panel** → **Munkamenetek** (Work Sessions) → **Műveletek** (Actions) → **Egyedi felhasználói letöltés**

Az action csak **tablófotózási módban** működő munkameneteknél jelenik meg, amelyeknek van almunkamenete.

## Használat

1. Navigálj a Munkamenetek listához a Filament admin panelen
2. Kattints a "Műveletek" (Actions) gombra egy tablófotózási munkamenetnél
3. Válaszd az "Egyedi felhasználói letöltés" opciót
4. A megjelenő modal ablakban:
   - Minden felhasználóhoz választhatsz típust (Összes/Retus/Tabló)
   - Csak azok a felhasználók jelennek meg, akiknek van képük
   - Nem jelenik meg a vendég felhasználó
5. Kattints a "Letöltés" gombra
6. A ZIP fájl automatikusan generálódik és letöltődik

## ZIP Struktúra

### Összes kép (retus + tabló)

```
123 - Work Session Name - Felhasznalo-kivalasztasok.zip
└── 123 - Work Session Name/
    ├── 456_Kovács János/
    │   ├── retusalando_kepek/
    │   │   ├── IMG_1234.jpg
    │   │   └── IMG_5678.jpg
    │   └── tablo_kep/
    │       └── IMG_9012.jpg
    └── 457_Nagy Péter/
        ├── retusalando_kepek/
        │   └── DSC_0001.jpg
        └── tablo_kep/
            └── DSC_0002.jpg
```

### Csak retus képek

```
123 - Work Session Name - Felhasznalo-kivalasztasok.zip
└── 123 - Work Session Name/
    ├── 456_Kovács János/
    │   ├── IMG_1234.jpg
    │   └── IMG_5678.jpg
    └── 457_Nagy Péter/
        └── DSC_0001.jpg
```

### Csak tabló kép

```
123 - Work Session Name - Felhasznalo-kivalasztasok.zip
└── 123 - Work Session Name/
    ├── 456_Kovács János/
    │   └── IMG_9012.jpg
    └── 457_Nagy Péter/
        └── DSC_0002.jpg
```

## Fájl Elnevezési Konvenciók

- **ZIP fájl**: `{work-session-id} - {work-session-name} - Felhasznalo-kivalasztasok.zip`
- **Root mappa**: `{work-session-id} - {work-session-name}/`
- **Felhasználó mappák**: `{child-session-id}_{user-name}/`
- **Retus mappa**: `retusalando_kepek/` (csak "Összes" típusnál)
- **Tabló mappa**: `tablo_kep/` (csak "Összes" típusnál)
- **Képfájlok**: Eredeti fájlnév (pl. `IMG_1234.jpg`)

## Duplikált Fájlnevek Kezelése

Azonos fájlnév esetén automatikus számozás:
- `IMG_1234.jpg`
- `IMG_1234 (1).jpg`
- `IMG_1234 (2).jpg`

## API Endpoint

### Custom User ZIP Download

```
GET /api/work-sessions/{workSession}/download-custom-user-zip?selections={JSON}
```

**Route Location**: `routes/web.php` (session-based authentication)

**Authentication**: Required (web session via `auth` middleware)

**Parameters**:
- `{workSession}` - Work Session ID (route parameter)
- `selections` - JSON encoded user selections (query parameter)
  - Format: `{"user_id": "type", ...}`
  - Types: `all`, `retus`, `tablo`

**Example**:
```
GET /api/work-sessions/123/download-custom-user-zip?selections=%7B%2245%22%3A%22all%22%2C%2246%22%3A%22retus%22%7D
```

Decoded selections:
```json
{
  "45": "all",
  "46": "retus"
}
```

**Response**:
- **Success**: ZIP file download (Content-Type: application/zip)
- **Error**: JSON with error message (HTTP 400/500)

## Backend Architecture

### Service Class

**File**: `app/Services/WorkSessionZipService.php`

**New Method**:
```php
public function generateCustomUserZip(WorkSession $workSession, array $userSelections): string
```

**Parameters**:
- `$workSession` - A munkamenet objektum
- `$userSelections` - Asszociatív tömb: `['user_id' => 'type']`

**Returns**: ZIP fájl elérési útvonal

**Logic**:
1. Létrehoz egy ZIP archívumot
2. Végigiterál a kiválasztott felhasználókon
3. Minden felhasználóhoz lekéri a `TabloUserProgress` adatokat
4. A kiválasztott típus alapján hozzáadja a képeket:
   - **all**: Mappás struktúra (retus + tabló almappák)
   - **retus**: Lapos struktúra (csak retus képek)
   - **tablo**: Lapos struktúra (csak tabló kép)
5. Visszaadja a ZIP fájl elérési útvonalát

### Controller

**File**: `app/Http/Controllers/Api/WorkSessionController.php`

**New Method**:
```php
public function downloadCustomUserZip(WorkSession $workSession): Response
```

**Responsibilities**:
- Validálja a tablófotózási módot
- Dekódolja a JSON-ben érkező kiválasztásokat
- Meghívja a `WorkSessionZipService::generateCustomUserZip()` metódust
- Visszaadja a letöltési választ (`deleteFileAfterSend(true)`)
- Hibakezelés és logolás

### Filament Action

**File**: `app/Filament/Resources/WorkSessions/Tables/WorkSessionsTable.php`

**Implementation**:
```php
Action::make('download_custom_user_zip')
    ->label('Egyedi felhasználói letöltés')
    ->icon('heroicon-o-user-group')
    ->color('warning')
    ->visible(fn (WorkSession $record) =>
        $record->is_tablo_mode &&
        $record->childSessions()->count() > 0
    )
    ->modalHeading('Felhasználók kiválasztása')
    ->modalDescription('Válaszd ki a felhasználókat és a képtípusokat...')
    ->form(function (WorkSession $record) {
        // Dinamikus form mezők generálása felhasználónként
    })
    ->action(function (array $data, WorkSession $record) {
        // URL építés kiválasztott felhasználókkal
    })
```

**Features**:
- Csak tablófotózási módban lévő munkameneteknél látható
- Dinamikus form generálás: minden felhasználóhoz egy Select mező
- Szűrés: csak azok a felhasználók, akiknek van képük
- Vendég felhasználók kizárása
- Validáció: legalább 1 felhasználó kiválasztása kötelező

## Error Handling

### Gyakori Hibák

1. **Nem tablófotózási mód**
   - HTTP 400: "A munkamenet nem tablófotózási módban van"
   - Megelőzés: Action csak `is_tablo_mode = true` esetén látható

2. **Nincs kiválasztás**
   - HTTP 400: "Nincs megadva felhasználó kiválasztás"
   - Validáció: Form action-ben ellenőrzés

3. **Érvénytelen formátum**
   - HTTP 400: "Érvénytelen kiválasztási formátum"
   - JSON dekódolás sikertelen

4. **Nincs kép**
   - Exception: "No photos found for selected users and types"
   - Ok: Felhasználóknak nincs képük vagy rossz kiválasztás

### Debugging

Laravel log ellenőrzés:
```bash
docker compose exec php-fpm tail -f /var/www/html/backend/storage/logs/laravel.log
```

Log formátum:
```
Failed to generate custom user ZIP
- work_session_id: 123
- error: [Exception message]
- trace: [Stack trace]
```

## Performance Considerations

### Memória Használat

- **Kis letöltés** (< 50 kép, < 50MB): Nincs probléma
- **Közepes letöltés** (50-200 kép, 50-200MB): 10-30 másodperc
- **Nagy letöltés** (> 200 kép, > 200MB): Időtúllépés kockázata

### Ajánlások

- PHP `memory_limit` minimum 256MB (ajánlott 512MB)
- `max_execution_time` minimum 120 másodperc nagy letöltéseknél
- Több mint 500 kép esetén háttérben futó job javasolt

## Security

### Authorization

- Endpoint `auth` middleware-rel védett (session-based)
- Csak bejelentkezett admin felhasználók érhetik el
- Filament panel automatikus jogosultság kezelés

### File Access

- Spatie Media Library secure file access
- Csak a munkamenethez tartozó képek elérhetők
- Nincs tetszőleges fájl hozzáférés

## Testing Scenarios

### Manuális Tesztelés

1. **Normál eset**: 2-3 felhasználó, különböző típusok (all/retus/tablo)
2. **Csak retus**: Minden felhasználónál "Csak retus képek"
3. **Csak tabló**: Minden felhasználónál "Csak tabló kép"
4. **Vegyes**: Különböző felhasználóknál különböző típusok
5. **Duplikált fájlnevek**: Több felhasználó azonos fájlnévvel
6. **Nagy letöltés**: 10+ felhasználó, 100+ kép

### Várt Eredmények

- ZIP fájl sikeresen letöltődik
- Fájlstruktúra megfelel a kiválasztásnak
- Eredeti képek sértetlenek
- Ideiglenes fájlok törlődnek letöltés után

## Related Files

- `app/Services/WorkSessionZipService.php` - Service logika
- `app/Http/Controllers/Api/WorkSessionController.php` - Controller endpoint
- `app/Filament/Resources/WorkSessions/Tables/WorkSessionsTable.php` - Filament UI action
- `routes/web.php` - Route definíció
- `app/Models/WorkSession.php` - Munkamenet modell
- `app/Models/TabloUserProgress.php` - Felhasználói progress modell
- `app/Models/User.php` - Felhasználó modell
- `app/Models/Photo.php` - Kép modell

## Changelog

### 2025-10-21 (v1.0)
- Initial implementation
- Egyedi felhasználói kiválasztás modal
- Dinamikus form generálás (Select minden felhasználóhoz)
- Típus választás: all/retus/tablo
- Szűrés: csak akiknek van képük
- Vendég felhasználók kizárása
- Mappás és lapos struktúra támogatás
- Duplikált fájlnevek kezelése
- Magyar lokalizáció minden UI elemben
- Filament 4 kompatibilitás (`Filament\Actions\*` namespace)