# Filament 4 Schema: Enum Based Conditional Visibility Fix

## Issue
Conditional `visible()` callbacks on `Section` components do not work when comparing against enum-backed radio field values in Filament 4 Schema.

### Symptoms
- Radio button has `->live()` flag
- Radio field uses enum class: `->options(TabloModeType::class)`
- Section visible callbacks use string comparison: `$get('tablo_mode_type') == 'fixed'`
- **Result:** Sections never become visible after radio selection

### Root Cause
When a form field uses an enum class (`->options(TabloModeType::class)`) and the model has enum casting:

```php
protected function casts(): array
{
    return [
        'tablo_mode_type' => TabloModeType::class,
    ];
}
```

The `$get()` function returns an **enum object** (e.g., `TabloModeType::FIXED`), **NOT a string** (`'fixed'`).

Therefore, comparing `$get('tablo_mode_type') == 'fixed'` will **always be false** because:
- Left side: `TabloModeType::FIXED` (enum object)
- Right side: `'fixed'` (string)
- Result: **No match!**

## Fix

### ❌ WRONG - String comparison
```php
Section::make('Fix retusálás beállítások')
    ->visible(fn (Get $get) =>
        $get('is_tablo_mode') && $get('tablo_mode_type') == 'fixed'  // ❌ NEVER matches!
    )
```

### ✅ CORRECT - Enum value comparison
```php
use App\Enums\TabloModeType;
use Filament\Schemas\Components\Utilities\Get;

Section::make('Fix retusálás beállítások')
    ->visible(fn (Get $get) =>
        $get('is_tablo_mode') && $get('tablo_mode_type') === TabloModeType::FIXED->value
    )
```

**Key changes:**
1. Import the enum class: `use App\Enums\TabloModeType;`
2. Use `->value` property: `TabloModeType::FIXED->value` (returns `'fixed'` string)
3. Use strict comparison: `===` instead of `==`
4. Type-hint `Get` utility: `fn (Get $get)`

## Alternative Solutions

### Option 1: Compare enum directly (if not cast in model)
```php
->visible(fn (Get $get) =>
    $get('tablo_mode_type') === 'fixed'  // If model does NOT cast to enum
)
```

### Option 2: Compare enum object (if you prefer)
```php
->visible(fn (Get $get) =>
    $get('tablo_mode_type') === TabloModeType::FIXED  // Direct enum comparison
)
```

However, **Option 2 may not work reliably** because Filament's `Get` utility might return string values during form building phase.

## Best Practice

Always use `->value` when comparing enum-backed fields in Filament visible() callbacks:

```php
// ✅ ALWAYS works
$get('enum_field') === EnumClass::CASE->value

// ⚠️ May not work
$get('enum_field') === EnumClass::CASE

// ❌ NEVER works (if enum cast in model)
$get('enum_field') === 'string_value'
```

## Examples from Codebase

### InvoicingProviderResource (CORRECT usage)
```php
use App\Enums\InvoicingProviderType;

Section::make('Számlázz.hu beállítások')
    ->visible(fn ($get) => $get('provider_type') === InvoicingProviderType::SzamlazzHu->value)
```

### PaymentMethodForm (CORRECT usage - no enum casting)
```php
Section::make('Átutalási adatok')
    ->visible(fn ($get): bool => $get('type') === 'transfer')
```
**Note:** This works because `type` field is NOT cast to enum in the model, so `$get('type')` returns a string.

## Files Affected
- `/backend/app/Filament/Resources/WorkSessions/Schemas/WorkSessionForm.php` (lines 262-349)

## Related Documentation
- Laravel Enum Casting: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting
- Filament 4 Schema Get Utility: https://filamentphp.com/docs/4.x/schemas/getting-started#reactive-fields
- Filament 4 Conditional Visibility: https://filamentphp.com/docs/4.x/schemas/components#conditional-visibility

## Deployment Steps

```bash
# 1. Apply fix (edit WorkSessionForm.php)
# 2. Syntax check
php -l app/Filament/Resources/WorkSessions/Schemas/WorkSessionForm.php

# 3. Autoload refresh
docker compose exec php-fpm composer dump-autoload -o -d /var/www/html/backend

# 4. Clear cache
docker compose exec php-fpm php /var/www/html/backend/artisan optimize:clear

# 5. Test in browser
# - Navigate to WorkSession edit page
# - Enable "Tablófotózási mód"
# - Select different radio options
# - Verify conditional sections appear/disappear correctly
```

## Testing Checklist
- [ ] Radio "fixed" → "Fix retusálás beállítások" section visible
- [ ] Radio "flexible" → "Rugalmas limit beállítások" section visible
- [ ] Radio "packages" → "Csomag alapú beállítások" section visible
- [ ] Toggle tablómód off → all sections hidden
- [ ] Create new WorkSession → default "fixed" selected and section visible
- [ ] Edit existing WorkSession → saved value persists and correct section visible

## Prevention

To avoid this issue in the future:

1. **ALWAYS check existing implementations** before using enum fields:
   ```bash
   grep -r "->visible.*->value" app/Filament/Resources/
   ```

2. **Create a coding standard document** for enum usage in Filament

3. **Add PHPStan rule** to detect string comparison against enum-cast fields

4. **Document this pattern in CLAUDE.md** under "Filament 4 Szabványok"

## Context
- **Feature:** Tablófotózási mód (WorkSession)
- **Enum:** `TabloModeType` (FIXED, FLEXIBLE, PACKAGES)
- **UI:** Conditional sections based on radio selection
- **Filament version:** 4.x
- **Laravel version:** 12.x
- **Date:** 2025-10-27
