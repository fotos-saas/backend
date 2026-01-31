# Download Manager - Letöltés Manager Funkció

## 📋 Feature Leírás

A **Download Manager** egy modern, egységesített letöltési felület a Filament admin panelben, amely lehetővé teszi:
- Felhasználók kiválasztását (multiple select)
- Képtípus kiválasztását (kiválasztott / retusálandó / tabló)
- Fájlnév formátum kiválasztását (eredeti / felhasználó neve / eredeti + EXIF)

Ez a funkció **helyettesíti** a korábbi két elavult letöltési módszert:
- ❌ `download_tablo_selections_zip` (törlésre került)
- ❌ `download_custom_user_zip` (törlésre került)

---

## 🎨 Form Mezők Specifikációja

### 1. Felhasználók (user_ids)

**Típus:** Multi-select (searchable)

**Mező:**
```php
Forms\Components\Select::make('user_ids')
    ->label('Felhasználók')
    ->multiple()
    ->searchable()
    ->options($usersWithPhotos)
    ->placeholder('Kezdj el gépelni...')
    ->required()
    ->helperText('Válaszd ki a letölteni kívánt felhasználókat')
```

**Funkció:**
- Csak azokat a felhasználókat listázza, akiknek van legalább 1 képük (`claimed_photo_ids` nem üres)
- Kiszűri a **guest** felhasználókat
- Kereshető név alapján

**Validáció:**
- Legalább 1 felhasználót ki kell választani
- Minden ID-nek létező felhasználónak kell lennie (`exists:users,id`)

---

### 2. Képtípus (photo_type)

**Típus:** Radio buttons (inline)

**Mező:**
```php
Forms\Components\Radio::make('photo_type')
    ->label('Képtípus')
    ->options([
        'claimed' => 'Kiválasztott képek',
        'retus' => 'Retusálandó képek',
        'tablo' => 'Tabló kép',
    ])
    ->descriptions([
        'claimed' => 'A felhasználó által kiválasztott összes kép',
        'retus' => 'Csak a retusálásra kijelölt képek',
        'tablo' => 'Csak a tabló képnek választott kép',
    ])
    ->default('claimed')
    ->required()
    ->inline()
```

**Típusok:**

| Érték    | Leírás | Forrás mező |
|----------|--------|-------------|
| `claimed` | Összes kiválasztott kép | `steps_data['claimed_photo_ids']` |
| `retus` | Retusálandó képek | `steps_data['retouch_photo_ids']` |
| `tablo` | Tabló kép (1 db) | `steps_data['tablo_photo_id']` |

**Default:** `claimed`

---

### 3. Fájlnév Formátum (filename_mode)

**Típus:** Radio buttons (inline)

**Mező:**
```php
Forms\Components\Radio::make('filename_mode')
    ->label('Fájlnév formátum')
    ->options([
        'original' => 'Eredeti fájlnév',
        'user_name' => 'Felhasználó neve',
        'original_exif' => 'Eredeti + EXIF metadata',
    ])
    ->descriptions([
        'original' => 'Megtartja az eredeti fájlnevet (pl. IMG_1234.jpg)',
        'user_name' => 'Átnevezi a felhasználó nevére (pl. Kovács János.jpg)',
        'original_exif' => 'Eredeti név + EXIF Title mezőbe írja a felhasználó nevét',
    ])
    ->default('original')
    ->required()
    ->inline()
```

**Módok:**

| Mód | Fájlnév példa | EXIF írás | Temp fájl | Használat |
|-----|---------------|-----------|-----------|-----------|
| `original` | `IMG_1234.jpg` | ❌ Nem | ❌ Nem | Gyors letöltés |
| `user_name` | `Kovács János.jpg` | ❌ Nem | ❌ Nem | Átnevezett fájlok |
| `original_exif` | `IMG_1234.jpg` | ✅ Igen | ✅ Igen | EXIF Title: "Kovács János" |

**Default:** `original`

**Fontos:** `original_exif` mód esetén:
- **ExifService** használata kötelező
- Temp fájlt hoz létre EXIF íráshoz
- Temp fájlok automatikus cleanup a ZIP bezárása után
- Ha exiftool nincs telepítve → fallback `original` módra

---

## 📦 ZIP Struktúra Példák

### Példa 1: Claimed képek, original fájlnév

```
123 - Fotózás 2025/
├── Kovács János/
│   ├── IMG_1234.jpg
│   ├── IMG_1235.jpg
│   └── IMG_1236.jpg
└── Nagy Anna/
    ├── DSC_4567.jpg
    └── DSC_4568.jpg
```

### Példa 2: Retusálandó képek, user_name mód

```
123 - Fotózás 2025/
├── Kovács János/
│   └── Kovács János.jpg
└── Nagy Anna/
    └── Nagy Anna.jpg
```

### Példa 3: Tabló kép, original_exif mód

```
123 - Fotózás 2025/
├── Kovács János/
│   └── IMG_1234.jpg (EXIF Title: "Kovács János")
└── Nagy Anna/
    └── DSC_4567.jpg (EXIF Title: "Nagy Anna")
```

**Szabályok:**
- ✅ **Root mappa:** `{work_session_id} - {work_session_name}`
- ✅ **User mappák:** `{user_name}` (sanitized)
- ✅ **Fájlnevek:** Duplikátumok esetén `(1)`, `(2)` suffix
- ✅ **Üres user mappák:** Ha nincs kép, a user kihagyásra kerül

---

## 🔌 API Endpoint Dokumentáció

### Endpoint

```
GET /api/work-sessions/{workSession}/download-manager-zip
```

### Autentikáció

**Middleware:** `auth` (csak bejelentkezett admin felhasználók)

### Request Paraméterek (Query String)

| Paraméter | Típus | Kötelező | Validáció | Leírás |
|-----------|-------|----------|-----------|--------|
| `user_ids[]` | array | ✅ Igen | `exists:users,id` | Felhasználó ID-k tömbje |
| `photo_type` | string | ✅ Igen | `in:claimed,retus,tablo` | Képtípus |
| `filename_mode` | string | ✅ Igen | `in:original,user_name,original_exif` | Fájlnév mód |

### Request Példa

```bash
GET /api/work-sessions/123/download-manager-zip?user_ids[]=5&user_ids[]=7&photo_type=claimed&filename_mode=original_exif
```

### Response

#### Sikeres letöltés (200 OK)

```
Content-Type: application/zip
Content-Disposition: attachment; filename="123 - Fotózás 2025.zip"

[Binary ZIP data]
```

#### Hiba - nincs kép (500 Internal Server Error)

```json
{
  "error": "Failed to generate ZIP file: No photos found for selected users and photo type"
}
```

#### Hiba - validáció (422 Unprocessable Entity)

```json
{
  "message": "The user ids field is required.",
  "errors": {
    "user_ids": ["The user ids field is required."]
  }
}
```

---

## 🛠️ EXIF Követelmények

### ExifTool telepítése

**Docker környezetben (Dockerfile):**

```dockerfile
RUN apt-get update && apt-get install -y \
    exiftool \
    && apt-get clean
```

**Helyi telepítés (macOS):**

```bash
brew install exiftool
```

**Helyi telepítés (Ubuntu/Debian):**

```bash
sudo apt-get install exiftool
```

### ExifService ellenőrzés

```bash
# ExifTool elérhető-e?
which exiftool

# PHP script teszt
docker compose exec php-fpm php /var/www/html/backend/artisan tinker

>>> app(\App\Services\ExifService::class)->isExifToolAvailable();
=> true
```

### EXIF Title írás példa

**Input:**
- `$sourcePath`: `/var/www/html/backend/storage/app/public/photos/IMG_1234.jpg`
- `$destPath`: `/tmp/temp_img_1234.jpg`
- `$title`: `"Kovács János"`

**Parancs:**
```bash
exiftool -Title="Kovács János" -overwrite_original /tmp/temp_img_1234.jpg
```

**Eredmény:**
- EXIF `Title` mező: `"Kovács János"`
- Fájl módosítva helyben (`-overwrite_original`)

**Ellenőrzés:**
```bash
exiftool -Title /tmp/temp_img_1234.jpg
# Output: Title: Kovács János
```

---

## 🐛 Troubleshooting

### 1. "exiftool not available" log

**Probléma:** ExifTool nincs telepítve a konténerben

**Megoldás:**
```bash
# 1. Ellenőrizd a Dockerfile-t
grep exiftool backend/Dockerfile

# 2. Rebuild Docker image
docker compose build php-fpm

# 3. Indítsd újra a konténereket
docker compose up -d
```

**Fallback:** Ha exiftool nincs telepítve, a rendszer automatikusan az `original` módra vált vissza.

---

### 2. "No photos found for selected users"

**Probléma:** A kiválasztott felhasználóknak nincs képük az adott típusban

**Megoldás:**
1. Ellenőrizd a `TabloUserProgress` tábla `steps_data` mezőjét
2. Győződj meg róla, hogy van `claimed_photo_ids`, `retouch_photo_ids`, vagy `tablo_photo_id`

```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker

>>> $progress = \App\Models\TabloUserProgress::where('user_id', 5)->first();
>>> $progress->steps_data;
```

---

### 3. "Failed to copy file" hiba

**Probléma:** Forrás fájl nem létezik vagy nem olvasható

**Megoldás:**
```bash
# 1. Ellenőrizd, hogy a media fájl létezik
docker compose exec php-fpm php /var/www/html/backend/artisan tinker

>>> $photo = \App\Models\Photo::find(123);
>>> $media = $photo->getFirstMedia('photo');
>>> $media->getPath();
>>> file_exists($media->getPath());
```

---

### 4. Temp fájlok nem törlődnek

**Probléma:** EXIF mód használata után temp fájlok maradnak `/tmp/`-ben

**Ellenőrzés:**
```bash
docker compose exec php-fpm ls -lah /tmp/ | grep uniqid
```

**Megoldás:**
- A `WorkSessionZipService::generateManagerZip()` automatikusan törli a temp fájlokat a ZIP bezárása után
- Ha manuális cleanup kell:

```bash
docker compose exec php-fpm find /tmp/ -name "*.jpg" -mtime +1 -delete
```

---

### 5. Duplikált fájlnevek

**Probléma:** Több felhasználónak azonos fájlneve van (`IMG_1234.jpg`)

**Megoldás:**
- A rendszer automatikusan hozzáad `(1)`, `(2)` suffixet
- Példa: `IMG_1234.jpg`, `IMG_1234 (1).jpg`, `IMG_1234 (2).jpg`

**Implementáció:** `WorkSessionZipService::resolveUniqueFilename()`

---

## 📊 Service Architektúra

```
┌─────────────────────────┐
│ WorkSessionsTable       │
│ (Filament Action)       │
└───────────┬─────────────┘
            │
            │ Form submit
            ▼
┌─────────────────────────┐
│ WorkSessionController   │
│ downloadManagerZip()    │
└───────────┬─────────────┘
            │
            │ Validation
            ▼
┌─────────────────────────┐       ┌─────────────────────┐
│ WorkSessionZipService   │◄──────┤ ExifService         │
│ generateManagerZip()    │       │ setTitleMetadata()  │
└───────────┬─────────────┘       └─────────────────────┘
            │
            │ Generate ZIP
            ▼
┌─────────────────────────┐
│ Response Download       │
│ (deleteFileAfterSend)   │
└─────────────────────────┘
```

---

## 🧪 Teszt Példák

### Manual Test (UI)

1. Navigálj: **Filament Admin → Work Sessions**
2. Válassz egy **tablo mode** work session-t
3. Kattints: **Műveletek → Letöltés manager**
4. Válaszd ki:
   - Felhasználók: `Kovács János`, `Nagy Anna`
   - Képtípus: `Kiválasztott képek`
   - Fájlnév formátum: `Eredeti + EXIF metadata`
5. Klikk: **Letöltés indítása**
6. Várj a letöltésre
7. Kicsomagolás és ellenőrzés:

```bash
unzip "123 - Fotózás 2025.zip"
cd "123 - Fotózás 2025"
ls -R

# EXIF ellenőrzés
exiftool "Kovács János/IMG_1234.jpg" | grep Title
# Expected: Title: Kovács János
```

---

### Automated Test (API)

```bash
# 1. Login és cookie mentés
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}' \
  -c cookies.txt

# 2. ZIP letöltés
curl -X GET "http://localhost:8000/api/work-sessions/123/download-manager-zip?user_ids[]=5&user_ids[]=7&photo_type=claimed&filename_mode=original_exif" \
  -b cookies.txt \
  -o download.zip

# 3. Ellenőrzés
unzip -l download.zip
```

---

## 📝 Changelog

### v1.0.0 - 2025-01-22

**Új funkciók:**
- ✅ ExifService osztály létrehozva
- ✅ WorkSessionZipService.generateManagerZip() metódus
- ✅ WorkSessionController.downloadManagerZip() endpoint
- ✅ WorkSessionsTable.download_manager action
- ✅ Route: `api.work-sessions.download-manager-zip`

**Elavult funkciók (törölve):**
- ❌ WorkSessionZipService.generateTabloSelectionsZip()
- ❌ WorkSessionZipService.generateCustomUserZip()
- ❌ WorkSessionController.downloadTabloSelectionsZip()
- ❌ WorkSessionController.downloadCustomUserZip()
- ❌ Route: `api.work-sessions.download-tablo-selections-zip`
- ❌ Route: `api.work-sessions.download-custom-user-zip`

**Breaking Changes:**
- Korábbi letöltési linkek (ha hardcode-olva voltak) már nem működnek
- ExifTool dependency hozzáadva (opcionális, fallback van)

---

## 🔗 Kapcsolódó Dokumentumok

- [CLAUDE.md](../CLAUDE.md) - Fejlesztési irányelvek
- [filament-standards.md](../docs/filament-standards.md) - Filament best practices
- [WorkSessionZipService.php](../app/Services/WorkSessionZipService.php) - Service implementáció
- [ExifService.php](../app/Services/ExifService.php) - EXIF service
