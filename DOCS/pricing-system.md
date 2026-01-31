# Pricing System Documentation

## Overview

A Photo Stack pricing rendszere teljes körű árlista-, csomag- és kuponkezelést biztosít. A rendszer támogatja a mennyiségi kedvezményeket, album-specifikus árlistákat és rugalmas kuponrendszert.

### Pricing Context Priority

**Ha WorkSession kontextus létezik:**
- WorkSession Package → WorkSession PriceList → **Default PriceList**
- Az Album beállításai teljesen figyelmen kívül maradnak WorkSession kontextusban

**Ha nincs WorkSession kontextus:**
- Album Package → Album PriceList → Default PriceList

## Database Structure

### PrintSizes (Nyomtatási méretek)
- `code`: Méret kódja (pl. "10x15", "13x18")
- `width_mm`: Szélesség milliméterben
- `height_mm`: Magasság milliméterben

### PriceLists (Árlisták)
- `active`: Boolean, csak egy aktív árlista lehet egyszerre
- `album_id`: Opcionális, album-specifikus árlista

### Prices (Árak)
- `price_list_id`: Melyik árlistához tartozik
- `print_size_id`: Melyik mérethez tartozik
- `gross_huf`: Bruttó ár forintban
- `digital_price_huf`: Digitális verzió ára
- `volume_discounts`: JSON array mennyiségi kedvezményekkel
  ```json
  [
    {"minQty": 50, "percentOff": 10},
    {"minQty": 100, "percentOff": 15}
  ]
  ```

### Packages (Csomagok)
- `name`: Csomag neve
- `album_id`: Opcionális, album-specifikus csomag
- `price`: Csomag ára forintban
- `selectable_photos_count`: Hány képet választhat ki a felhasználó
- `coupon_policy`: Kupon házirend ('all', 'none', 'specific')
- `allowed_coupon_ids`: JSON array engedélyezett kupon ID-kkal

### PackageItems (Csomag tételek)
- `package_id`: Melyik csomaghoz tartozik
- `print_size_id`: Melyik méret
- `quantity`: Mennyiség

### Coupons (Kuponok)
- `code`: Kuponkód (egyedi)
- `type`: "percent" vagy "amount"
- `value`: Érték (százalék vagy HUF)
- `enabled`: Aktív-e
- `expires_at`: Lejárati dátum (nullable)
- `min_order_value`: Minimum rendelési érték HUF-ban
- `max_usage`: Maximum felhasználási szám
- `usage_count`: Jelenlegi felhasználások száma
- `allowed_emails`: JSON array engedélyezett e-mailekkel
- `allowed_album_ids`: JSON array engedélyezett album ID-kkal
- `description`: Leírás

### WorkSessions (Munkamenetek)
- `name`: Munkamenet neve
- `description`: Leírás
- `status`: 'active', 'inactive', 'archived'
- `digit_code_enabled`: 6 számjegyű kód engedélyezve
- `digit_code`: 6 számjegyű belépési kód
- `share_enabled`: Megosztási link engedélyezve
- `share_token`: Egyedi megosztási token
- `coupon_policy`: Kupon házirend ('all', 'none', 'specific')
- `allowed_coupon_ids`: JSON array engedélyezett kupon ID-kkal

## API Endpoints

### GET /api/pricing-rules
Returns all active pricing rules:
```json
[
  {
    "id": 1,
    "size": "10x15",
    "price": 300,
    "currency": "HUF",
    "volumeDiscounts": [
      {"minQty": 50, "percentOff": 10},
      {"minQty": 100, "percentOff": 15}
    ]
  }
]
```

### POST /api/cart/calculate
Calculate cart total with volume discounts:
```json
{
  "items": [
    {"photoId": 1, "size": "10x15", "quantity": 60}
  ]
}
```

Response:
```json
{
  "items": [
    {
      "photoId": 1,
      "size": "10x15",
      "quantity": 60,
      "unitPrice": 270,
      "total": 16200
    }
  ],
  "subtotal": 16200,
  "discounts": [],
  "total": 16200
}
```

### GET /api/coupons/{code}
Validate and get coupon details.

**Query Parameters:**
- `package_id` (optional): Package context for validation
- `work_session_id` (optional): Work session context for validation (has priority over package)

Returns 403 if coupon is not valid in the given context.

### GET /api/coupons
Get available coupons for context.

**Query Parameters:**
- `package_id` (optional): Filter by package restrictions
- `work_session_id` (optional): Filter by work session restrictions (has priority)

## Filament Admin

### Navigation
All pricing-related resources are under **"Csomagbeállítások"** navigation group:
1. Nyomtatási méretek (Print Sizes)
2. Árlisták (Price Lists)
3. Csomagok (Packages)
4. Kuponok (Coupons)

### Print Sizes Management
- Create standard photo sizes
- Define dimensions in millimeters
- View how many prices reference each size

### Price Lists Management
- Create global or album-specific price lists
- Only one price list can be active at a time
- Use **Prices** relation manager to:
  - Add prices for each print size
  - Set gross price (HUF)
  - Set digital price (optional)
  - Configure volume discounts

### Packages Management
- Create predefined photo packages
- Set package price and selectable photos count
- Use **Items** relation manager to add package contents
- Can be global or album-specific
- **Coupon Policy Options:**
  - **Minden érvényes kupon használható** (default)
  - **Csak meghatározott kuponok** - select specific coupons
  - **Egyik kupon sem használható** - disable all coupons

### Coupons Management
- Create percentage or fixed-amount coupons
- Set expiration dates
- Limit usage count
- Restrict to specific emails
- Set minimum order value

### WorkSessions Management
- Create work sessions with access codes or share links
- Configure coupon policy (overrides package settings)
- **Coupon Policy Options:**
  - **Minden érvényes kupon használható** (default)
  - **Csak meghatározott kuponok** - select specific coupons
  - **Egyik kupon sem használható** - disable all coupons
- **Note:** WorkSession coupon settings have priority over Package coupon settings

## Volume Discounts

Volume discounts are applied automatically based on quantity:

```php
// Example: 10x15 photo
// Base price: 300 HUF
// 50+ pcs: 10% off → 270 HUF/pc
// 100+ pcs: 15% off → 255 HUF/pc
// 200+ pcs: 20% off → 240 HUF/pc
```

Discount calculation logic in `Price::calculatePriceForQuantity()`:
1. Sort volume discounts by `minQty` descending
2. Find the first discount where quantity >= minQty
3. Apply percentage discount to base price

## Coupon Validation

### Basic Validation
Coupon validation logic in `Coupon::isValidForOrder()`:
1. Check if enabled
2. Check expiration date
3. Check usage limit (usage_count < max_usage)
4. Check minimum order value
5. Check allowed emails (if set)
6. Check allowed albums (if set)

### Context-Based Validation (NEW)

Coupons can now be restricted by Package or WorkSession context.

**Priority Order:** WorkSession > Package > None

#### Package Coupon Policy
Packages can define which coupons are allowed:
- **all** (default): All valid coupons accepted
- **specific**: Only selected coupons accepted
- **none**: No coupons accepted

#### WorkSession Coupon Policy
WorkSessions can override package restrictions:
- **all** (default): All valid coupons accepted
- **specific**: Only selected coupons accepted
- **none**: No coupons accepted

**Note:** WorkSession settings always override Package settings.

#### Service Layer
`CouponService` handles context validation:
```php
// Check if coupon is valid in context
$isValid = $couponService->validateCouponForContext(
    $coupon, 
    $package, 
    $workSession
);

// Get available coupons for context
$coupons = $couponService->getAvailableCouponsForContext(
    $package,
    $workSession
);
```

## Photo URL Optimization

Photos now use direct storage URLs instead of API endpoints:

### Before (API endpoint):
```
http://localhost:8000/api/photos/123/preview?w=300
```

### After (direct storage URL):
```
http://localhost:8000/storage/1/conversions/photo-thumb.jpg
```

This eliminates the N+1 problem and prevents 429 Too Many Requests errors when loading 100+ photos.

### Implementation
- `Photo::getThumbUrl()` - Returns 300x300 thumbnail URL
- `Photo::getPreviewUrl()` - Returns 1200x1200 preview URL
- `Photo::getWatermarkedUrl()` - Returns 1200x1200 watermarked URL

All use Spatie Media Library's `getUrl()` method which returns direct storage URLs.

## Seeded Data

### Print Sizes
- 9x13, 10x15, 13x18, 15x21, 18x24, 20x30

### Packages
- **Alapcsomag**: 10× 10x15 + 5× 13x18
- **Prémium csomag**: 20× 10x15 + 10× 13x18 + 5× 15x21
- **Teljes csomag**: 50× 10x15 + 20× 13x18 + 10× 15x21 + 5× 18x24

### Coupons
- **WELCOME10**: 10% off, min 1000 HUF
- **SUMMER20**: 20% off, min 5000 HUF
- **FIXED500**: 500 HUF off, min 3000 HUF
- **BIRTHDAY15**: 15% off, min 2000 HUF

## Testing

### Backend Tests
```bash
# Test pricing API
curl http://localhost:8000/api/pricing-rules

# Test direct photo URL
# Open in browser: http://localhost:8000/storage/1/conversions/photo-thumb.jpg
```

### Frontend Tests
1. Open album with 100+ photos
2. Scroll through photos - should load without 429 errors
3. Test selection and quantity adjustments
4. Verify access mode banners (viewer, buyer, selector)

### Admin Tests
1. Navigate to "Csomagbeállítások" menu
2. Create new print size
3. Create price list and add prices with volume discounts
4. Create package with multiple items
5. Create coupon with restrictions

## Storage Link

Ensure storage link exists:
```bash
docker compose exec php php artisan storage:link
```

This creates a symlink: `public/storage` → `storage/app/public`

## Future Enhancements

- [ ] Package pricing calculator
- [ ] Bulk price updates
- [ ] Price history/versioning
- [ ] Coupon usage analytics
- [ ] Album-specific pricing overrides
- [ ] Seasonal pricing rules

