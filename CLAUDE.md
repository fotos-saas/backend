# PhotoStack Backend - Laravel 12 + PHP 8.3

> **Projekt áttekintés:** [`../CLAUDE.md`](../CLAUDE.md) | **Frontend:** [`../frontend/CLAUDE.md`](../frontend/CLAUDE.md)

---

## KRITIKUS SZABÁLYOK

| # | Szabály |
|---|---------|
| 1 | **Controller max 300 sor** — Ha nagyobb, bontsd Action-be vagy Service-be |
| 2 | **SOHA inline validate()** — MINDIG FormRequest class |
| 3 | **SOHA shell_exec()** — MINDIG Symfony Process komponens |
| 4 | **ILIKE pattern** — QueryHelper::safeLikePattern() |
| 5 | **FormData ID-k** — array_map('intval', $ids) (mert FormData stringet küld) |
| 6 | **Minden model-hez Policy** |
| 7 | **BACKUP adatbázis műveletek előtt** |
| 8 | **Minden API válasz magyarul** |

---

## Architektúra Pattern-ek

### Controller — Action Pattern

Ha az üzleti logika >30 sor vagy újrahasználható, emeld Action class-ba:

```php
// app/Actions/Partner/ExportContactsExcelAction.php
class ExportContactsExcelAction
{
    public function execute(TabloProject $project, array $filters): string
    {
        // Üzleti logika itt
    }
}

// Controller-ben:
public function exportExcel(ExportRequest $request, TabloProject $project)
{
    return (new ExportContactsExcelAction())->execute($project, $request->validated());
}
```

**Mikor Action?**
- Üzleti logika >30 sor
- Több controller-ből is hívható
- Tesztelhető izoláltan

**Mikor Service?**
- Állapotot tart (injectable singleton)
- Külső rendszer integráció (Stripe, Billingo, CompreFace)
- Több Action-ból is használt közös logika

### FormRequest Validáció (KÖTELEZŐ)

```php
// app/Http/Requests/Api/Partner/UpdateProjectSettingsRequest.php
class UpdateProjectSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,archived'],
        ];
    }
}
```

### Policy Jogosultságkezelés

```php
// Controller-ben:
$this->authorize('update', $project);

// Vagy middleware-rel:
Route::put('/projects/{project}', [...])->can('update', 'project');
```

### API Response (ApiResponseTrait)

A base Controller automatikusan használja — minden controller örökli:

```php
return $this->successResponse($data, 'Sikeres művelet');
return $this->errorResponse('Hiba történt', 400);
return $this->notFoundResponse('Nem található');
return $this->paginatedResponse($paginator, 'Lista betöltve');
return $this->createdResponse($data, 'Létrehozva');
return $this->noContentResponse(); // 204
```

---

## Könyvtárstruktúra

### Route fájlok (routes/api/)

| Fájl | Tartalom |
|------|----------|
| auth.php | Login, regisztráció, jelszó, 2FA, magic link |
| public.php | Health, pricing, cart, orders, fotók, client |
| partner.php | Partner dashboard, projektek, iskolák, csapat, előfizetés |
| marketer.php | Ügyintéző route-ok |
| admin.php | Super admin rendszer |
| tablo.php | Tabló management + tabló frontend |
| help.php | Help system (chatbot, tour, knowledge base) |
| dev.php | Fejlesztői route-ok (csak lokálisan) |

### Controller csoportok (app/Http/Controllers/Api/)

| Csoport | Db | Felelősség |
|---------|-----|------------|
| Auth/ | 8 | Bejelentkezés, regisztráció, session, 2FA |
| Partner/ | 27 | Partner dashboard, projektek, galéria, export, webshop |
| Tablo/ | 18 | Tabló workflow, fórum, newsfeed, szavazás, vendég |
| Admin/ | 3 | Bug report, help cikkek/túrák |
| Marketer/ | 3 | Ügyintéző projekt/kontakt/QR kezelés |
| Root | 20+ | Publikus API-k (order, album, photo, subscription) |

### Fontosabb könyvtárak

| Könyvtár | Tartalom |
|----------|----------|
| app/Actions/ | ~55 action class (Auth, Partner, Tablo, SuperAdmin, Order) |
| app/Http/Requests/ | ~100 FormRequest (MINDEN validáció ide!) |
| app/Policies/ | 23 policy (MINDEN model-hez!) |
| app/Services/ | ~85 service (üzleti logika, integráció) |
| app/Models/ | ~123 Eloquent model |
| app/Helpers/ | QueryHelper (safeLikePattern) |
| app/DTOs/ | Data Transfer Object-ek |
| app/Enums/ | PHP Enum-ok |
| app/Jobs/ | Queue job-ok |
| app/Mail/ | Mailable class-ok |
| app/Middleware/ | SecurityHeaders, CheckPartnerFeature, stb. |

---

## Security Konvenciók

### ILIKE Query (SQL Injection védelem)

```php
use App\Helpers\QueryHelper;

// HELYES
$query->where('name', 'ILIKE', QueryHelper::safeLikePattern($search));

// TILOS
$query->where('name', 'ILIKE', "%{$search}%");
```

### Shell Command Futtatás

```php
use Symfony\Component\Process\Process;

// HELYES
$process = new Process(['exiftool', '-json', $filePath]);
$process->run();
```

### Path Traversal Védelem

```php
$realPath = realpath($requestedPath);
if (!$realPath || !str_starts_with($realPath, $allowedBaseDir)) {
    return $this->forbiddenResponse('Érvénytelen elérési út');
}
```

### Rate Limiting

- Login: Dual rate limit (IP + email)
- Tabló login: Erősített limit
- API: Laravel throttle middleware

---

## Adatbázis

- **PostgreSQL 17** — CSAK a szerveren fut (89.167.19.19)!
- **Lokálisan NINCS DB** — php -l szintaxis ellenőrzéshez, tesztek Docker-ben
- DB query-k: ssh root@89.167.19.19 majd Coolify container-ben artisan tinker / psql

### Migráció konvenciók

```php
// Mindig nullable vagy default érték új oszlopnál (backward compat)
$table->string('new_column')->nullable();

// Index ha gyakori keresés
$table->index('column_name');
```

---

## Konfiguráció

| Config fájl | Tartalom |
|-------------|----------|
| config/plans.php | Előfizetési csomagok limitek (SINGLE SOURCE OF TRUTH) |
| config/stripe.php | Stripe integráció |
| config/billing.php | Számlázási beállítások |
| config/face-recognition.php | CompreFace arcfelismerés |
| config/anthropic.php | Claude AI integráció |
| config/image.php | Képfeldolgozás beállítások |

---

## Tesztelés

```bash
# Szintaxis ellenőrzés (lokálisan is megy)
php -l app/Http/Controllers/Api/Partner/PartnerProjectController.php

# Tesztek (Docker kell hozzá - PostgreSQL)
php artisan test
```

---

## Kód Stílus

```php
// Return type MINDIG
public function index(IndexRequest $request): JsonResponse

// Property promotion constructor-ban
public function __construct(
    private readonly PartnerPhotoService $photoService,
) {}

// Enum használat magic string helyett
use App\Enums\ProjectStatus;
$project->status = ProjectStatus::Active;
```

---

## ACE Learned Strategies

<!-- ACE:START - Do not edit manually -->
skills[5	]{id	section	content	helpful	harmful	neutral}:
  deployment-00001	deployment	Find Coolify container by git commit hash in image tag for artisan	1	0	0
  deployment-00002	deployment	Ignore fotos-* containers for Laravel artisan; use Coolify sk8g* containers	1	0	0
  project_structure-00003	project_structure	Backend and frontend are separate git repos; commit from each subdirectory	1	0	0
  testing-00004	testing	Use `php -l` for local syntax verification; tests need Docker postgres	1	0	0
  project_structure-00005	project_structure	Services use domain subdirectories (Services/Tablo/); resolve paths via Glob first	1	0	0
  branding_flow-00006	branding_flow	Partner branding data must be in ALL session responses (login + validate-session); validate-session overwrites project data on page reload	1	0	0
  partner_resolution-00007	partner_resolution	TabloPartner->Partner link uses partner_id FK (priority) + email fallback (legacy); update ResolvesPartner, CheckPartnerFeature, User.getEffectivePartner when changing	1	0	0
  feature_gate-00008	feature_gate	CheckPartnerFeature middleware: checkTabloPartnerFeature must check subscriber Partner for ALL features, not just forum/polls	1	0	0
<!-- ACE:END -->
