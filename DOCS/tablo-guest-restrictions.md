# Tablo Guest User Restrictions

## Áttekintés

A vendég felhasználók (share/preview tokenek) CSAK OLVASÁSI jogosultsággal rendelkeznek.
Minden módosító műveletnél (`POST`, `PUT`, `PATCH`, `DELETE`) a `RequireFullAccess` middleware blokkolja őket.

## Token Típusok

| Token Name | Token Type | Jogosultság | Leírás |
|-----------|-----------|-------------|---------|
| `tablo-auth-token` | `code` | **TELJES ÍRÁS/OLVASÁS** | Kódos belépés - véglegesítés, sablon választás, adatmódosítás |
| `tablo-share-token` | `share` | **CSAK OLVASÁS** | Megosztott link - előnézet, adat megtekintés |
| `tablo-preview-token` | `preview` | **CSAK OLVASÁS** | Előnézet link - korlátozott olvasás |

## Implementáció

### RequireFullAccess Middleware

**Fájl:** `backend/app/Http/Middleware/RequireFullAccess.php`

```php
/**
 * Middleware: RequireFullAccess
 *
 * Ellenőrzi, hogy a felhasználó teljes jogosultsággal rendelkezik-e.
 * Csak kódos belépéssel (tablo-auth-token) rendelkező felhasználók férhetnek hozzá.
 */
class RequireFullAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        $tokenName = $token->name;
        $allowedTokens = ['tablo-auth-token'];

        if (!in_array($tokenName, $allowedTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Ez a művelet csak teljes jogosultsággal érhető el. Megosztott vagy előnézeti linkkel nem lehetséges.',
                'error' => 'insufficient_permissions',
            ], 403);
        }

        return $next($request);
    }
}
```

### Védett Endpoint-ok

#### Template Chooser - Írás

**RequireFullAccess middleware alkalmazva:**

- `POST /api/tablo-frontend/templates/{id}/select` - Template kiválasztása
- `DELETE /api/tablo-frontend/templates/{id}/select` - Template törlése
- `PATCH /api/tablo-frontend/templates/{id}/priority` - Prioritás frissítése

**Publikus (vendég is elérheti):**

- `GET /api/tablo-frontend/templates` - Template lista
- `GET /api/tablo-frontend/templates/categories` - Kategóriák
- `GET /api/tablo-frontend/templates/{id}` - Template részletei
- `GET /api/tablo-frontend/templates/selections/current` - Kiválasztott template-ek

#### Projekt Adatok - Írás

**RequireFullAccess middleware alkalmazva:**

- `PUT /api/tablo-frontend/contact` - Kapcsolattartó adatok módosítása
- `POST /api/tablo-frontend/update-schedule` - Fotózás időpontjának frissítése

**Publikus (vendég is elérheti):**

- `GET /api/tablo-frontend/project-info` - Projekt információk
- `GET /api/tablo-frontend/samples` - Minta képek
- `GET /api/tablo-frontend/order-data` - Rendelés adatok

#### Finalization (már védett CheckFinalizationAccess-el)

- `GET /api/tablo-frontend/finalization` - Véglegesítés adatok
- `POST /api/tablo-frontend/finalization` - Véglegesítés mentése
- `POST /api/tablo-frontend/finalization/draft` - Draft mentése
- `POST /api/tablo-frontend/finalization/upload` - Fájl feltöltése
- `DELETE /api/tablo-frontend/finalization/file` - Fájl törlése
- `POST /api/tablo-frontend/finalization/preview-pdf` - PDF előnézet

## API Válasz - validate-session

A `/api/tablo-frontend/validate-session` endpoint visszaadja a token típusát és jogosultságokat:

```json
{
  "valid": true,
  "project": { ... },
  "tokenType": "code" | "share" | "preview",
  "isGuest": false | true,
  "canFinalize": false | true
}
```

### Mező magyarázat

- **tokenType**: Token típusa (`code`, `share`, `preview`)
- **isGuest**: `true` ha share/preview token (csak olvasás)
- **canFinalize**: `true` csak code token esetén (véglegesítés és írás engedélyezett)

## Frontend Route Guards

Az Angular frontend a `canFinalize` és `isGuest` mezők alapján eldönti:

1. **Template választó gomb megjelenítése** - csak ha `canFinalize: true`
2. **Kapcsolattartó szerkesztés** - csak ha `isGuest: false`
3. **Ütemezés frissítés** - csak ha `isGuest: false`
4. **Template kiválasztás/törlés** - csak ha `canFinalize: true`

## Hibakezelés

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Nincs érvényes session."
}
```

### 403 Forbidden (vendég próbál írni)

```json
{
  "success": false,
  "message": "Ez a művelet csak teljes jogosultsággal érhető el. Megosztott vagy előnézeti linkkel nem lehetséges.",
  "error": "insufficient_permissions"
}
```

## Tesztelés

### Route lista ellenőrzése

```bash
docker compose exec php-fpm php /var/www/html/backend/artisan route:list --path=tablo-frontend/templates --verbose
```

Várt eredmény:
- GET endpoint-ok: NEM tartalmazzák a RequireFullAccess middleware-t
- POST/PUT/PATCH/DELETE endpoint-ok: TARTALMAZZÁK a RequireFullAccess middleware-t

### Middleware működés tesztelése

1. **Code token (teljes jogosultság)**
   ```bash
   curl -X POST https://api.example.com/api/tablo-frontend/templates/1/select \
     -H "Authorization: Bearer {code-token}" \
     -H "Content-Type: application/json"
   # Várt: 200 OK vagy 201 Created
   ```

2. **Share token (csak olvasás)**
   ```bash
   curl -X POST https://api.example.com/api/tablo-frontend/templates/1/select \
     -H "Authorization: Bearer {share-token}" \
     -H "Content-Type: application/json"
   # Várt: 403 Forbidden + "insufficient_permissions"
   ```

3. **Preview token (csak olvasás)**
   ```bash
   curl -X POST https://api.example.com/api/tablo-frontend/templates/1/select \
     -H "Authorization: Bearer {preview-token}" \
     -H "Content-Type: application/json"
   # Várt: 403 Forbidden + "insufficient_permissions"
   ```

## Deployment Checklist

- [ ] RequireFullAccess middleware létrehozva
- [ ] Middleware regisztrálva (Laravel 11: teljes osztálynév használata)
- [ ] Route-ok frissítve (template select/deselect/priority + contact + schedule)
- [ ] validate-session endpoint visszaadja `isGuest` mezőt
- [ ] composer dump-autoload futtatva
- [ ] optimize:clear futtatva
- [ ] Route lista ellenőrizve (`artisan route:list`)
- [ ] PHP szintaxis ellenőrizve
- [ ] Production-re deploy (git push → git pull)
- [ ] Production cache tisztítva

## Kapcsolódó Fájlok

- `backend/app/Http/Middleware/RequireFullAccess.php` - Middleware implementáció
- `backend/app/Http/Middleware/CheckFinalizationAccess.php` - Véglegesítés middleware (példa)
- `backend/routes/api.php` - Route definíciók
- `backend/app/Http/Controllers/Api/Tablo/TabloTemplateController.php` - Template controller
- `backend/app/Http/Controllers/Api/Tablo/TabloFrontendController.php` - Frontend controller

---

**Utolsó frissítés:** 2026-01-09
**Verzió:** 1.0
