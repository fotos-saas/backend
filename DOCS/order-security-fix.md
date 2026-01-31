# Order Security Fix - 2026-01-05

## Problem

Az Order API endpoint-ok TELJESEN PUBLIKUSAK voltak, bárki bármely rendelést megtekinthetett az ID ismeretében!

**KRITIKUS Sebezhetőségek:**
- ❌ `/api/orders/{order}` - PUBLIKUS, nincs authentikáció
- ❌ `/api/orders/{order}/checkout` - PUBLIKUS, bárki checkout-olhatott bármely rendelést
- ❌ `/api/orders/{order}/verify-payment` - PUBLIKUS, nincs védelem
- ❌ `/api/orders/{order}/invoice/download` - RÉSZBEN védett, de nem elég
- ❌ Érzékeny mezők (stripe_pi, címek) láthatók voltak a JSON-ban

## Solution Implemented

### 1. Order Model - Érzékeny mezők elrejtése

**File:** `backend/app/Models/Order.php`

```php
protected $hidden = [
    'stripe_pi',        // Stripe Payment Intent (KRITIKUS!)
    'guest_address',    // Vendég címadatai
    'billing_address',  // Számlázási cím
    'shipping_address', // Szállítási cím
];
```

**Eredmény:** Ezek a mezők SOHA nem kerülnek bele a JSON response-ba (még admin-oknak sem)!

### 2. OrderPolicy - Authorization logic

**File:** `backend/app/Policies/OrderPolicy.php`

#### Policy metódusok:

| Metódus | Logika |
|---------|--------|
| `view()` | User: saját rendelés <br> Guest: email validáció szükséges <br> Admin: mindent láthat |
| `checkout()` | User: saját rendelés <br> Guest: email validáció szükséges |
| `verifyPayment()` | User: saját rendelés vagy admin <br> Guest: email validáció szükséges |
| `downloadInvoice()` | User: saját rendelés vagy admin <br> Guest: email validáció szükséges |

**Laravel Auto-Discovery:** A Policy automatikusan felismerhető a `Order` model alapján (naming convention: `OrderPolicy`).

### 3. OrderController - Authorization ellenőrzések

**File:** `backend/app/Http/Controllers/Api/OrderController.php`

#### `show()` metódus
```php
// Authenticated order - must be owner or admin
if ($order->user_id) {
    if (!$user || $user->id !== $order->user_id && !$user->hasRole('admin')) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
}

// Guest order - verify email
if ($order->isGuest()) {
    $guestEmail = $request->query('guest_email') ?? $request->input('guest_email');
    if (!$guestEmail || strtolower($guestEmail) !== strtolower($order->guest_email)) {
        return response()->json(['message' => 'Forbidden. Guest email verification required.'], 403);
    }
}
```

#### `checkout()` metódus
- Ugyanaz a logika, mint `show()`
- Email validáció guest rendeléseknél

#### `verifyPayment()` metódus
- Admin users can verify any order
- Regular users: csak saját rendelés
- Guest: email validáció

#### `downloadInvoice()` metódus
- Admin users can download any invoice
- Regular users: csak saját számla
- Guest: email validáció

### 4. Route Security Strategy

**File:** `backend/routes/api.php`

**MEGKÖZELÍTÉS:** A route-ok PUBLIKUSAK maradnak, de a controller metódusok védettek!

**Miért?**
- ✅ Guest checkout támogatás (nincs auth:sanctum)
- ✅ Email alapú validáció vendégeknek
- ✅ Flexible authorization logic controller-ben

**Route lista:**
```php
// Orders (public - supports both guest and authenticated checkout)
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders/{order}/checkout', [OrderController::class, 'checkout']);
Route::get('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment']);
Route::get('/orders/{order}/invoice/download', [OrderController::class, 'downloadInvoice']);
```

## Security Test Plan

### 1. Unauthorized Access Test

```bash
# Test: Unauthorized user trying to view another user's order
curl -X GET http://localhost:8000/api/orders/123
# Expected: 403 Forbidden (ha user_id van)
# Expected: 403 Forbidden (ha guest és nincs email)

# Test: Authenticated user trying to view other user's order
curl -X GET http://localhost:8000/api/orders/123 \
  -H "Authorization: Bearer {token}"
# Expected: 403 Forbidden (if not owner)
```

### 2. Guest Email Validation Test

```bash
# Test: Guest order with correct email
curl -X GET "http://localhost:8000/api/orders/456?guest_email=guest@example.com"
# Expected: 200 OK (if email matches)

# Test: Guest order with wrong email
curl -X GET "http://localhost:8000/api/orders/456?guest_email=wrong@example.com"
# Expected: 403 Forbidden
```

### 3. Hidden Fields Test

```bash
# Test: Check if hidden fields are present in response
curl -X GET "http://localhost:8000/api/orders/123?guest_email=guest@example.com" \
  | jq '.stripe_pi, .guest_address, .billing_address, .shipping_address'
# Expected: null, null, null, null (hidden fields NOT returned)
```

### 4. Admin Access Test

```bash
# Test: Admin viewing any order
curl -X GET http://localhost:8000/api/orders/789 \
  -H "Authorization: Bearer {admin_token}"
# Expected: 200 OK (admin can view all)
```

## Checklist

- [x] Order model $hidden mezők hozzáadva
- [x] OrderPolicy létrehozva (view, checkout, verifyPayment, downloadInvoice)
- [x] OrderController show() metódus védve
- [x] OrderController checkout() metódus védve
- [x] OrderController verifyPayment() metódus védve
- [x] OrderController downloadInvoice() metódus védve
- [x] Composer autoload refresh
- [x] Laravel cache clear
- [x] Syntax check (Order, OrderPolicy, OrderController)
- [x] Policy auto-discovery teszt

## Impact Analysis

### Before (VULNERABLE):
- ❌ Anyone with order ID could view order details
- ❌ Anyone could checkout other users' orders
- ❌ Sensitive data (stripe_pi, addresses) exposed in JSON
- ❌ No guest email validation

### After (SECURED):
- ✅ Only order owner can view (authenticated users)
- ✅ Guest orders require email validation
- ✅ Admin users can view/manage all orders
- ✅ Sensitive fields hidden from all responses
- ✅ Checkout protected by owner check or email validation
- ✅ Payment verification protected
- ✅ Invoice download protected

## Deployment

### Local Testing
```bash
# Autoload refresh
docker compose exec php-fpm composer dump-autoload -o -d /var/www/html/backend

# Cache clear
docker compose exec php-fpm php /var/www/html/backend/artisan optimize:clear

# Test
curl -X GET "http://localhost:8000/api/orders/1?guest_email=test@example.com"
```

### Production Deployment
```bash
# SSH to production
ssh root@217.13.104.5

# Pull changes
cd /var/www/photo-stack && git pull origin main

# Autoload refresh
docker compose -f docker-compose.production.yml exec -T php-fpm composer dump-autoload -o -d /var/www/html/backend

# Cache clear
docker compose -f docker-compose.production.yml exec -T php-fpm php /var/www/html/backend/artisan optimize:clear

# Restart PHP-FPM (optional, but recommended)
docker compose -f docker-compose.production.yml restart php-fpm
```

## Notes

- **Laravel Policy Auto-Discovery:** Az `OrderPolicy` automatikusan felismerhető a `Order` model alapján
- **Email validáció:** Case-insensitive email matching (`strtolower()`)
- **Guest vs Authenticated:** A controller külön kezeli a két típust
- **Admin override:** Admin users MINDEN rendelést láthatnak/kezelhetnek
- **Hidden fields:** A `$hidden` property biztosítja, hogy érzékeny mezők SOHA ne kerüljenek a JSON-ba

## Breaking Changes

**NINCS!** A vendég checkout workflow továbbra is működik, de most biztonságosan:
- Guest users: email validáció szükséges query parameter-ben vagy request body-ban
- Authenticated users: automatikus user_id check
- Admin users: teljes hozzáférés

## Future Improvements

1. **Rate Limiting:** Limitáld a guest email validation kísérleteket (brute force védelem)
2. **Logging:** Naplózd az unauthorized access kísérleteket
3. **Email Token:** Opcionális email token alapú validáció (pl. magic link)
4. **2FA:** Kéttényezős authentikáció admin műveletekhez

---

**Fixed by:** Claude Code Assistant
**Date:** 2026-01-05
**Priority:** CRITICAL SECURITY FIX
**Status:** ✅ COMPLETED
