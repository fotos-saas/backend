# Tablo Photo Conflict Email Implementation

## Áttekintés

Ez a dokumentum a tablófotó konfliktus email küldési rendszer implementációját írja le. Amikor egy felhasználó véglegesíti a rendelését, és ezáltal egyes képeket átvesz más felhasználóktól (FIFO elv), azok a felhasználók email értesítést kapnak.

## Implementált Komponensek

### 1. Migration

**Fájl:** `database/migrations/2025_10_21_120000_add_tablo_photo_conflict_to_email_events.php`

**Mit csinál:**
- Hozzáadja a `tablo_photo_conflict` event type-ot az `email_events` tábla enum constraint-jéhez

**Futtatás:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan migrate
```

### 2. Seeder

**Fájl:** `database/seeders/TabloPhotoConflictEmailSeeder.php`

**Mit csinál:**
- Létrehozza az `EmailTemplate`-et magyar nyelvű HTML email-lel
- Létrehozza az `EmailEvent`-et `tablo_photo_conflict` event_type-pal
- Összekapcsolja a kettőt

**Futtatás:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan db:seed --class=TabloPhotoConflictEmailSeeder
```

### 3. EmailVariableService Bővítés

**Fájl:** `app/Services/EmailVariableService.php`

**Új változók:**
- `{{removed_count}}` - Eltávolított képek száma
- `{{removed_photo_ids}}` - Eltávolított képek ID-i (vesszővel elválasztva)
- `{{winner_user_name}}` - Nyertes felhasználó neve

**Használat:**
```php
$variables = $emailVariableService->resolveVariables(
    user: $affectedUser,
    workSession: $userWorkSession,
    authData: [
        'removed_count' => count($removedPhotoIds),
        'removed_photo_ids' => $removedPhotoIds,
        'winner_user_name' => $winnerUser->name,
    ]
);
```

### 4. TabloWorkflowService Email Küldés

**Fájl:** `app/Services/TabloWorkflowService.php`

**Metódus:** `sendPhotoRemovedEmail()`

**Működés:**
1. Log-ol minden információt
2. Lekéri az `EmailEvent`-et (`event_type = 'tablo_photo_conflict'`)
3. Ellenőrzi, hogy létezik-e az EmailTemplate
4. Összeállítja a változókat (user, work_session, authData)
5. Elküldi az emailt az `EmailService`-en keresztül
6. Hibakezelés try-catch blokkal

**Automatikus hívás:**
A metódust automatikusan hívja a `removeConflictingPhotosFromOtherUsers()` metódus minden érintett felhasználóra.

## Email Template Tartalom

### Subject
```
Néhány kép már nem elérhető - {{album_title}}
```

### Body Főbb Elemei
- **Figyelmeztetés blokk** - Sárga háttérrel, eltávolított képek száma
- **Információs blokk** - Kék háttérrel, FIFO elv magyarázata
- **Akciógomb** - "Folytatom a munkamenetet" gomb (work_session_url linkkel)
- **Footer** - Partner elérhetőségek, site name

### Használt Változók
- `{{user_name}}` - Érintett felhasználó neve
- `{{removed_count}}` - Eltávolított képek száma
- `{{work_session_url}}` - Munkamenet belépési URL
- `{{partner_email}}` - Partner email cím
- `{{site_name}}` - Oldal neve
- `{{current_year}}` - Aktuális év

## Tesztelés

### 1. Migration Futtatása

```bash
# LOCAL környezet
docker compose exec php-fpm php /var/www/html/backend/artisan migrate

# PRODUCTION környezet
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan migrate'
```

### 2. Seeder Futtatása

```bash
# LOCAL
docker compose exec php-fpm php /var/www/html/backend/artisan db:seed --class=TabloPhotoConflictEmailSeeder

# PRODUCTION
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan db:seed --class=TabloPhotoConflictEmailSeeder'
```

### 3. EmailEvent Ellenőrzése

```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
use App\Models\EmailEvent;

\$event = EmailEvent::where('event_type', 'tablo_photo_conflict')->first();

if (\$event) {
    echo 'EmailEvent found: ' . \$event->id . PHP_EOL;
    echo 'Template: ' . \$event->emailTemplate->name . PHP_EOL;
    echo 'Active: ' . (\$event->is_active ? 'Yes' : 'No') . PHP_EOL;
} else {
    echo 'EmailEvent NOT found!' . PHP_EOL;
}
"
```

### 4. Email Küldés Tesztelése (Manuális)

```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
use App\Models\User;
use App\Services\TabloWorkflowService;

// Teszt felhasználók
\$affectedUser = User::where('email', 'test@example.com')->first();
\$winnerUser = User::where('email', 'winner@example.com')->first();

if (!\$affectedUser || !\$winnerUser) {
    echo 'Test users not found. Create them first!' . PHP_EOL;
    exit;
}

// Service példány
\$service = app(TabloWorkflowService::class);

// Privát metódus hívása reflection-nal
\$reflection = new \ReflectionClass(\$service);
\$method = \$reflection->getMethod('sendPhotoRemovedEmail');
\$method->setAccessible(true);

// Email küldés
\$method->invoke(\$service, \$affectedUser, [1, 2, 3], \$winnerUser);

echo 'Email sent! Check logs for details.' . PHP_EOL;
"
```

### 5. Logok Ellenőrzése

```bash
# Log stream
docker compose logs php-fpm --tail=50 --follow | grep "TabloPhotoConflict"

# Specific log file
docker compose exec php-fpm tail -f /var/www/html/backend/storage/logs/laravel.log | grep "TabloPhotoConflict"
```

### 6. Teljes Workflow Teszt

**Szimuláció:**
1. Felhasználó A kiválaszt 10 képet
2. Felhasználó B kiválaszt 10 képet (5 közös)
3. Felhasználó A véglegesít → megkapja mind a 10 képet
4. Felhasználó B-nek email érkezik, hogy 5 kép eltávolítva

```bash
# Teszt script (példa)
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
use App\Models\User;
use App\Models\WorkSession;
use App\Models\Album;
use App\Services\TabloWorkflowService;

// Setup
\$parentSession = WorkSession::where('is_tablo_mode', true)->first();
\$parentAlbum = \$parentSession->albums()->first();
\$service = app(TabloWorkflowService::class);

// Felhasználók
\$userA = User::find(1);
\$userB = User::find(2);

// UserA véglegesít 5 képet
\$photoIds = [1, 2, 3, 4, 5];

\$result = \$service->createChildAlbumAndMovePhotos(\$userA, \$parentSession, \$parentAlbum, \$photoIds);

// Konfliktusok kezelése
\$service->removeConflictingPhotosFromOtherUsers(\$userA, \$parentSession, \$result['moved']);

echo 'Workflow test completed. Check logs and emails.' . PHP_EOL;
"
```

## Troubleshooting

### Email Nem Érkezik

**1. Ellenőrizd az EmailEvent létezését:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
echo App\Models\EmailEvent::where('event_type', 'tablo_photo_conflict')->exists() ? 'EXISTS' : 'NOT FOUND';
"
```

**2. Ellenőrizd az EmailTemplate létezését:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
\$event = App\Models\EmailEvent::where('event_type', 'tablo_photo_conflict')->first();
echo \$event && \$event->emailTemplate ? 'Template OK' : 'Template MISSING';
"
```

**3. Ellenőrizd a SMTP beállításokat:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
echo 'MAIL_MAILER: ' . config('mail.default');
echo 'MAIL_FROM: ' . config('mail.from.address');
"
```

**4. Ellenőrizd az EmailLog táblát:**
```bash
docker compose exec php-fpm php /var/www/html/backend/artisan tinker --execute="
\$logs = App\Models\EmailLog::where('event_type', 'tablo_photo_conflict')->latest()->take(5)->get();
foreach (\$logs as \$log) {
    echo 'Log ID: ' . \$log->id . ' | Status: ' . \$log->status . ' | Recipient: ' . \$log->recipient_email . PHP_EOL;
}
"
```

### "Class not found" Hiba

```bash
# Autoload rebuild
docker compose exec php-fpm composer dump-autoload -o -d /var/www/html/backend

# Cache clear
docker compose exec php-fpm php /var/www/html/backend/artisan optimize:clear
```

### Migration Constraint Error

Ha a migration hibát dob:
```bash
# Check current constraint
docker compose exec db psql -U photo_stack -d photo_stack -c "
SELECT conname, pg_get_constraintdef(oid)
FROM pg_constraint
WHERE conrelid = 'email_events'::regclass;
"

# Manual constraint drop
docker compose exec db psql -U photo_stack -d photo_stack -c "
ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_check;
"

# Re-run migration
docker compose exec php-fpm php /var/www/html/backend/artisan migrate
```

## Production Deployment

### 1. Backup

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T db pg_dump -U photo_stack -d photo_stack > backup-$(date +%Y%m%d%H%M%S).sql'
```

### 2. Pull Changes

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && git pull origin main'
```

### 3. Run Migration

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan migrate --force'
```

### 4. Run Seeder

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan db:seed --class=TabloPhotoConflictEmailSeeder --force'
```

### 5. Clear Cache

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm composer dump-autoload -o -d /var/www/html/backend && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan optimize:clear'
```

### 6. Verify

```bash
ssh root@217.13.104.5 'cd /var/www/photo-stack && docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan tinker --execute="echo App\Models\EmailEvent::where(\"event_type\", \"tablo_photo_conflict\")->exists() ? \"OK\" : \"ERROR\";"'
```

## Sikerkritériumok

- ✅ Migration sikeresen lefutott
- ✅ Seeder létrehozta az EmailEvent-et és EmailTemplate-et
- ✅ EmailEvent `event_type = 'tablo_photo_conflict'` létezik
- ✅ EmailTemplate tartalmaz magyar nyelvű HTML email-t
- ✅ EmailVariableService támogatja a `removed_count`, `removed_photo_ids`, `winner_user_name` változókat
- ✅ TabloWorkflowService `sendPhotoRemovedEmail()` metódus implementálva
- ✅ Email küldés sikeres (EmailLog.status = 'sent')
- ✅ Változók helyesen helyettesítődnek az email-ben
- ✅ Érintett felhasználók megkapják az emailt

## Kapcsolódó Fájlok

- `app/Services/TabloWorkflowService.php` - Email küldés logika
- `app/Services/EmailService.php` - Email küldés infrastruktúra
- `app/Services/EmailVariableService.php` - Változók kezelése
- `app/Models/EmailEvent.php` - EmailEvent model
- `app/Models/EmailTemplate.php` - EmailTemplate model
- `database/migrations/2025_10_21_120000_add_tablo_photo_conflict_to_email_events.php` - Migration
- `database/seeders/TabloPhotoConflictEmailSeeder.php` - Seeder

## Changelog

### 2025-10-21
- ✅ Initial implementation
- ✅ Migration created for `tablo_photo_conflict` event type
- ✅ Seeder created for EmailEvent and EmailTemplate
- ✅ EmailVariableService extended with new variables
- ✅ TabloWorkflowService.sendPhotoRemovedEmail() implemented
- ✅ Full documentation created
