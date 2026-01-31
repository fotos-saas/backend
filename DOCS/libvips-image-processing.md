# libvips Image Processing - Best Practices & Tapasztalatok

## Összefoglaló

A photo-stack projektben a képfeldolgozást **ImageMagick-ről libvips-re** váltottuk át, ami **4-8x gyorsabb** feldolgozást és **10x kevesebb memória** használatot eredményezett.

---

## Teljesítmény Összehasonlítás

| Könyvtár | Sebesség | Memória | HEIC támogatás |
|----------|----------|---------|----------------|
| **libvips** | 🥇 Leggyorsabb | ~10x kevesebb | ✅ Natív (vips-heif) |
| **ImageMagick** | 🥈 Közepes | Sok | ✅ Natív |
| **Pillow (Python)** | 🥉 Lassabb | Több | ⚠️ Plugin kell |
| **GD** | 🥉 Lassabb | Közepes | ❌ Nincs |

### Valós Benchmark (fotopack.kepvalaszto.hu)

**Előtte (Spatie + ImageMagick):**
- 17 HEIC kép: ~8-10 perc
- ~15-30 másodperc / kép

**Utána (libvips):**
- 17 HEIC kép: ~1 perc
- ~3-4 másodperc / kép

**Eredmény: 4-8x gyorsulás!**

---

## Miért libvips?

### 1. Streaming Architecture
- **ImageMagick**: Egész képet betölti RAM-ba
- **libvips**: "Demand-driven" - csak a szükséges részt tölti be

### 2. Horizontal Threading
- Automatikus párhuzamosítás CPU core-ok között
- Nem kell manuálisan thread-eket kezelni

### 3. Alacsony Memória Footprint
- 100MB-os HEIC feldolgozása ~50MB RAM-mal
- ImageMagick ugyanezt ~500MB+ RAM-mal csinálja

---

## PHP Integráció

### Composer Package
```bash
composer require jcupitt/vips
```

### Docker Függőségek (Alpine)
```dockerfile
# libvips és HEIC támogatás
RUN apk add --no-cache \
    vips-dev \
    vips-tools \
    vips-heif
```

### PHP FFI Engedélyezés
```ini
; php-ffi.ini
ffi.enable=true
zend.max_allowed_stack_size=-1
```

---

## Kód Példák

### Alapvető Resize + JPEG Mentés
```php
use Jcupitt\Vips\Image as VipsImage;

$image = VipsImage::newFromFile($inputPath, ['access' => 'sequential']);

// Alpha channel kezelés (JPEG-hez flatten kell)
if ($image->hasAlpha()) {
    $image = $image->flatten(['background' => [255, 255, 255]]);
}

// sRGB colorspace (Display P3, Adobe RGB → sRGB)
$image = $image->colourspace('srgb');

// Resize (aspect ratio megtartva)
$scale = min($maxSize / $image->width, $maxSize / $image->height);
$image = $image->resize($scale);

// EXIF orientáció alkalmazása + metaadatok törlése
$image = $image->autorot();

// Mentés
$image->jpegsave($outputPath, [
    'Q' => 85,              // Quality
    'strip' => true,        // EXIF törlés
    'optimize_coding' => true,
    'interlace' => true,    // Progressive JPEG
]);
```

### Thumbnail Generálás Sharpen-nel
```php
$image = VipsImage::newFromFile($inputPath, ['access' => 'sequential']);

if ($image->hasAlpha()) {
    $image = $image->flatten(['background' => [255, 255, 255]]);
}

$image = $image->colourspace('srgb');

// Resize
$scale = min($maxSize / $image->width, $maxSize / $image->height);
$image = $image->resize($scale);

// Auto-rotate
$image = $image->autorot();

// Sharpen (Spatie sharpen(10) megfelelője)
$image = $image->sharpen(['sigma' => 0.5, 'm1' => 1, 'm2' => 2]);

$image->jpegsave($outputPath, [
    'Q' => 85,
    'strip' => true,
    'optimize_coding' => true,
    'interlace' => true,
]);
```

---

## Spatie Media Library Integráció

### Probléma
A Spatie Media Library alapból **ImageMagick** vagy **GD** drivereket használ:
```php
// config/media-library.php
'image_driver' => env('MEDIA_IMAGE_DRIVER', 'imagick'),
```

A `spatie/image` csomag **NEM támogatja a libvips-et**!

### Megoldás
A `registerMediaConversions()` metódust üresen hagyjuk, és saját Job-ban csináljuk a thumbnail generálást:

```php
// ConversionMedia.php
public function registerMediaConversions(?Media $media = null): void
{
    // DISABLED: Handled by GenerateThumbnailsJob using libvips
}
```

```php
// GenerateThumbnailsJob.php
use Jcupitt\Vips\Image as VipsImage;

private function generateThumbnailWithVips(string $inputPath, string $outputPath, int $maxSize): void
{
    $image = VipsImage::newFromFile($inputPath, ['access' => 'sequential']);
    // ... libvips feldolgozás
    $image->jpegsave($outputPath, ['Q' => 85, 'strip' => true]);
}
```

---

## Gyakori Hibák és Megoldások

### 1. "VipsImage not found"
```bash
# Ellenőrizd, hogy libvips telepítve van-e
vips --version
# Várt: vips-8.17.3 vagy újabb

# PHP-ben
php -r "var_dump(extension_loaded('ffi'));"
# Várt: bool(true)
```

### 2. HEIC nem támogatott
```bash
# Alpine-on
apk add vips-heif

# Ellenőrzés
vips heifload
# Nem szabad hibát dobnia
```

### 3. Memória hiba nagy képeknél
```php
// Használj sequential access-t!
$image = VipsImage::newFromFile($path, ['access' => 'sequential']);

// NE használd:
$image = VipsImage::newFromFile($path); // Ez random access, több memória
```

### 4. Színek eltérőek
```php
// MINDIG konvertálj sRGB-re!
$image = $image->colourspace('srgb');
```

---

## Python vs PHP libvips

**Kérdés:** Gyorsabb lenne Python (pyvips)?

**Válasz:** NEM. Mindkettő ugyanazt a C libvips könyvtárat hívja FFI-n keresztül. A tényleges képfeldolgozás sebessége azonos.

| Szempont | PHP (jcupitt/vips) | Python (pyvips) |
|----------|-------------------|-----------------|
| Sebesség | ✅ Azonos | ✅ Azonos |
| Integráció | ✅ Laravel natív | ⚠️ Külön service kell |
| Komplexitás | ✅ Egyszerű | ⚠️ PHP↔Python kommunikáció |

**Konklúzió:** Maradj a PHP libvips-nél, nincs előnye a Python váltásnak.

---

## Pillow vs libvips

**Kérdés:** Mi a helyzet a Pillow-val?

**Válasz:** A Pillow **lassabb** mint a libvips!

- Resize: libvips **2-4x gyorsabb**
- Memória: libvips **5-10x kevesebb**
- Nagy képek: libvips streaming, Pillow egész kép RAM-ba

---

## Érintett Fájlok

| Fájl | Változás |
|------|----------|
| `backend/app/Jobs/ConvertImageBatchJob.php` | libvips HEIC→JPEG konverzió |
| `backend/app/Jobs/GenerateThumbnailsJob.php` | libvips thumbnail generálás |
| `backend/app/Models/ConversionMedia.php` | Spatie konverziók kikapcsolva |
| `backend/Dockerfile` | vips-dev, vips-tools, vips-heif |
| `backend/docker/php/php-ffi.ini` | FFI engedélyezés |
| `backend/config/image.php` | `'driver' => 'vips'` |

---

## Telepítési Checklist

- [ ] `composer require jcupitt/vips`
- [ ] Dockerfile: `vips-dev vips-tools vips-heif`
- [ ] php-ffi.ini: `ffi.enable=true`
- [ ] config/image.php: `'driver' => 'vips'`
- [ ] ConversionMedia: `registerMediaConversions()` üres
- [ ] GenerateThumbnailsJob: libvips implementáció
- [ ] ConvertImageBatchJob: libvips implementáció
- [ ] Teszt: `vips --version` a containerben

---

## Összegzés

A libvips a legjobb választás képfeldolgozáshoz:
- **4-8x gyorsabb** mint ImageMagick
- **10x kevesebb memória**
- **Natív HEIC támogatás**
- PHP és Python **azonos sebességű** (ugyanaz a C lib)
- Pillow **lassabb** alternative

A photo-stack projektben ez **~1 perc vs ~10 perc** különbséget jelent 17 HEIC képnél!
