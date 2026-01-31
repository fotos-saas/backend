# User Model - Biztonsági Javítások

**Dátum:** 2026-01-05
**Fájl:** `backend/app/Models/User.php`

## Probléma

A User model két kritikus biztonsági problémát tartalmazott:

### 1. Mass Assignment Vulnerability (Privilege Escalation)

**KRITIKUS HIBA:** A következő mezők fillable-ként voltak meghatározva:
- `role` - Felhasználó szerepköre (super_admin, photo_admin, customer, guest, tablo)
- `class_id` - Iskolai osztály hozzárendelés
- `tablo_partner_id` - Tablo partner hozzárendelés

**Következmény:** Bárki, aki API-n keresztül vagy form-on keresztül küld adatokat, beállíthatja magát super_admin-ná vagy más privilegizált szerepkörbe.

**Példa támadásra:**
```php
// Támadó egy egyszerű regisztrációs kérésnél:
POST /api/users
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "role": "super_admin"  // ← Privilege escalation!
}

// Laravel feldolgozás:
User::create($request->all()); // ← 'role' is fillable → sikeres privilege escalation
```

### 2. Sensitive Data Exposure

**HIBA:** A `$hidden` tömbből hiányzott a `guest_token` mező.

**Következmény:** API válaszokban, JSON serialize-nél és log-okban megjelentek az érzékeny tokenek.

**Példa adatszivárgásra:**
```php
// API response:
GET /api/users/123

Response:
{
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com",
    "guest_token": "abc123def456",  // ← ÉRZÉKENY! NEM SZABADNA LÁTHATÓ LENNI!
    "password": "$2y$10$...",         // ← JELSZÓ HASH LÁTHATÓ!
    "remember_token": "xyz789"        // ← SESSION TOKEN LÁTHATÓ!
}
```

---

## Megoldás

### 1. Mass Assignment Protection

**ELŐTTE (❌ ROSSZ):**
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    'role',              // ❌ KRITIKUS HIBA!
    'class_id',          // ❌ KRITIKUS HIBA!
    'tablo_partner_id',  // ❌ KRITIKUS HIBA!
    'address',
    'first_login_at',
    'password_set',
    'guest_token',
];
```

**UTÁNA (✅ HELYES):**
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    'address',
    'first_login_at',
    'password_set',
    'guest_token',
];

protected $guarded = [
    'id',
    'role',              // ✅ Védve
    'class_id',          // ✅ Védve
    'tablo_partner_id',  // ✅ Védve
    'email_verified_at',
    'created_at',
    'updated_at',
];
```

### 2. Sensitive Data Protection

**ELŐTTE (❌ ROSSZ):**
```php
protected $hidden = [
    'password',
    'remember_token',
    // guest_token HIÁNYZIK! ❌
];
```

**UTÁNA (✅ HELYES):**
```php
protected $hidden = [
    'password',
    'remember_token',
    'guest_token',  // ✅ Hozzáadva
];
```

---

## Explicit Assignment Használata

Ha szükséges beállítani a védett mezőket (pl. admin interface-en keresztül), használj **Spatie Permission `assignRole()` metódust**:

```php
// ✅ HELYES - Spatie Permission használata (JAVASOLT!)
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
]);

// Szerepkör hozzárendelés Spatie Permission-nal
$user->assignRole(User::ROLE_CUSTOMER);

// ✅ HELYES - Explicit assignment (csak ha nincs Spatie Permission)
$user = User::find($id);
$user->role = 'super_admin';  // Csak admin jogosultsággal!
$user->save();

// vagy

$user->update([
    'name' => $request->name,
    'email' => $request->email,
]);
$user->role = $validatedRole;  // Külön, validált szerepkör hozzárendelés
$user->save();
```

**SOHA NE HASZNÁLD:**
```php
// ❌ ROSSZ - Mass assignment védett mezőkkel
User::create($request->all());  // Ha $request tartalmaz 'role'-t → KIVÉTEL!

// ❌ ROSSZ - Unguard használata
User::unguard();
User::create($request->all());
User::reguard();

// ❌ ROSSZ - 'role' a create()-ben (mass assignment vulnerability!)
User::create([
    'name' => 'John',
    'email' => 'john@example.com',
    'role' => 'super_admin',  // ← TILOS!
]);
```

---

## Javított Kódrészletek

A biztonsági javítások után az alábbi fájlokban módosítottam a `User::create()` hívásokat:

### 1. AuthController - register() metódus (sor: 349-363)

**ELŐTTE (❌ ROSSZ):**
```php
$user = User::create([
    'name' => $request->input('name'),
    'email' => $request->input('email'),
    'password' => Hash::make($request->input('password')),
    'phone' => $request->input('phone'),
    'role' => User::ROLE_CUSTOMER,  // ❌ Mass assignment vulnerability!
]);
```

**UTÁNA (✅ HELYES):**
```php
// SECURITY: Create user without 'role' (mass assignment protected)
$user = User::create([
    'name' => $request->input('name'),
    'email' => $request->input('email'),
    'password' => Hash::make($request->input('password')),
    'phone' => $request->input('phone'),
]);

// Assign customer role explicitly (not mass assignable for security)
$user->assignRole(User::ROLE_CUSTOMER);
```

### 2. AuthController - guestLogin() metódus (sor: 100-113)

**ELŐTTE (❌ ROSSZ):**
```php
$guestUser = User::create([
    'name' => 'Guest-'.$workSession->id.'-'.Str::random(6),
    'email' => null,
    'password' => null,
    'role' => User::ROLE_GUEST,  // ❌ Mass assignment vulnerability!
]);
```

**UTÁNA (✅ HELYES):**
```php
// SECURITY: Create user without 'role' (mass assignment protected)
$guestUser = User::create([
    'name' => 'Guest-'.$workSession->id.'-'.Str::random(6),
    'email' => null,
    'password' => null,
]);

// Assign guest role explicitly (not mass assignable for security)
$guestUser->assignRole(User::ROLE_GUEST);
```

### 3. AuthController - tabloLogin() metódus (sor: 177-189)

**ELŐTTE (❌ ROSSZ):**
```php
$tabloGuestUser = User::create([
    'email' => $guestEmail,
    'name' => 'Tablo Guest - ' . $tabloProject->display_name,
    'password' => null,
    'role' => User::ROLE_GUEST,  // ❌ Mass assignment vulnerability!
]);
```

**UTÁNA (✅ HELYES):**
```php
// SECURITY: Create user without 'role' (mass assignment protected)
$tabloGuestUser = User::create([
    'email' => $guestEmail,
    'name' => 'Tablo Guest - ' . $tabloProject->display_name,
    'password' => null,
]);

// Assign guest role explicitly (not mass assignable for security)
$tabloGuestUser->assignRole(User::ROLE_GUEST);
```

### 4. AuthController - tabloShareLogin() metódus (sor: 250-262)

Azonos javítás mint a tabloLogin() metódusnál.

### 5. AuthController - tabloPreviewLogin() metódus (sor: 320-332)

Azonos javítás mint a tabloLogin() metódusnál.

### 6. AuthController - sendWorkSessionInvites() metódus (sor: 838-850)

**ELŐTTE (❌ ROSSZ):**
```php
$user = User::create([
    'email' => $email,
    'name' => explode('@', $email)[0],
    'role' => 'user',  // ❌ Mass assignment vulnerability!
    'password' => null,
    'password_set' => false,
]);
```

**UTÁNA (✅ HELYES):**
```php
// SECURITY: Create user without 'role' (mass assignment protected)
$user = User::create([
    'email' => $email,
    'name' => explode('@', $email)[0],
    'password' => null,
    'password_set' => false,
]);

// Assign role explicitly (not mass assignable for security)
$user->assignRole('user');
```

---

## Tesztelés

### 1. Mass Assignment Protection Teszt

```php
// Teszt: Fillable mezők nem tartalmazzák a role-t
$user = new User();
$user->fill(['name' => 'Test', 'email' => 'test@test.com', 'role' => 'super_admin']);

assert($user->role === null); // ✅ role NEM állítódik be fillable-n keresztül

// Explicit assignment működik
$user->role = 'super_admin';
assert($user->role === 'super_admin'); // ✅ Explicit assignment OK
```

**Teszt eredmény:**
```
Fillable mezők után - role: NULL       ✅ PASS
Explicit assignment után - role: super_admin  ✅ PASS
```

### 2. Hidden Attributes Teszt

```php
// Teszt: Hidden mezők nem jelennek meg toArray()-ben
$user = User::first();
$array = $user->toArray();

assert(!isset($array['password']));       // ✅ Password rejtett
assert(!isset($array['remember_token'])); // ✅ Remember token rejtett
assert(!isset($array['guest_token']));    // ✅ Guest token rejtett
```

**Teszt eredmény:**
```
Password látható: NEM (OK)             ✅ PASS
Remember token látható: NEM (OK)       ✅ PASS
Guest token látható: NEM (OK)          ✅ PASS
```

---

## Biztonsági Ellenőrző Lista

- [x] `role` eltávolítva a `$fillable`-ból
- [x] `class_id` eltávolítva a `$fillable`-ból
- [x] `tablo_partner_id` eltávolítva a `$fillable`-ból
- [x] `$guarded` tömb hozzáadva védett mezőkkel
- [x] `guest_token` hozzáadva a `$hidden` tömbhöz
- [x] Kommentek hozzáadva a biztonsági okokkal
- [x] Mass assignment protection tesztelve
- [x] Hidden attributes tesztelve
- [x] Explicit assignment működése ellenőrizve

---

## Best Practices

1. **SOHA ne add hozzá a `role`-t vagy más privilegizált mezőket a `$fillable`-hoz!**
2. **Minden érzékeny mező legyen a `$hidden` tömbben!**
3. **Használj Form Request validation-t API endpoint-okhoz!**
4. **Admin interface-en explicit authorization check-et használj szerepkör módosításhoz!**
5. **Audit log-ot készíts minden szerepkör változtatáshoz!**

---

## További Információk

- Laravel Mass Assignment: https://laravel.com/docs/11.x/eloquent#mass-assignment
- Laravel API Resources: https://laravel.com/docs/11.x/eloquent-resources
- OWASP Mass Assignment: https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/05-Testing_for_Mass_Assignment

---

**Következtetés:** A User model most biztonságos a mass assignment privilege escalation és sensitive data exposure támadásokkal szemben. Az explicit assignment lehetőséget ad kontrollált módosításokra, miközben véd a véletlen vagy rosszindulatú manipulációtól.
