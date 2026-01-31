# 📧 Email Rendszer - Gyors Útmutató

## 🚀 Gyors Kezdés

### 1. Alapértelmezett Sablonok Betöltése

```bash
cd backend
php artisan db:seed --class=EmailTemplatesSeeder
php artisan db:seed --class=EmailEventsSeeder
```

Ez létrehozza az alapértelmezett email sablonokat és eseményeket.

### 2. Admin Panel Hozzáférés

Navigálj a következő menüpontokhoz:
- **Email Sablonok** - Email sablonok kezelése
- **Email Események** - Automatikus kiküldések beállítása
- **Email Napló** - Kiküldött emailek követése

### 3. Első Email Sablon Kipróbálása

1. Menj az **Email Sablonok** menüpontra
2. Válassz egy sablont (pl. "welcome_email")
3. Kattints az **Előnézet** gombra → Látod a sablont teszt adatokkal
4. Kattints a **Teszt Email Küldése** gombra → Küldd el magadnak

### 4. Automatikus Email Beállítása

1. Menj az **Email Események** menüpontra
2. Nézd meg a létező eseményeket
3. Szerkesztheted őket vagy újat hozhatsz létre
4. Állítsd be:
   - **Esemény típusa** - Mikor menjen ki
   - **Email sablon** - Melyik sablon
   - **Címzett típus** - Kinek menjen
   - **Aktív** - BE/KI kapcsoló

## 🎯 Gyakorlati Példák

### Példa 1: Új felhasználó üdvözlő email

Az admin felületen hozz létre egy új felhasználót:
1. Menj a **Felhasználók** menübe
2. Kattints **Új Felhasználó**
3. Töltsd ki az űrlapot
4. Mentsd el
5. → Automatikusan kimegy az üdvözlő email (ha be van állítva esemény)

Ellenőrizd az **Email Napló**-ban hogy elment-e!

### Példa 2: Új album értesítő email

1. Menj a **Fotózások** menübe
2. Hozz létre egy új albumot
3. → Az album létrejött esemény kiváltódik
4. Ellenőrizd az **Email Napló**-t

### Példa 3: Egyedi email küldése

1. Menj az **Email Sablonok** menübe
2. Válassz egy sablont
3. Kattints a **Teszt Email** gombra a műveletek között
4. Add meg az email címet
5. Küld el
6. Ellenőrizd az **Email Napló**-t

## 📝 Új Sablon Létrehozása

1. **Email Sablonok** → **Új Email Sablon**
2. Töltsd ki:
   - **Azonosító kulcs**: `my_custom_email`
   - **Email tárgya**: `Fontos értesítés, {user_name}!`
   - **Email tartalom**: Használd a WYSIWYG szerkesztőt
3. Nyisd le az **Elérhető változók** szekciót → Lásd az összes változót
4. Használd a változókat: `{user_name}`, `{site_name}`, stb.
5. Mentsd el
6. Kattints az **Előnézet** gombra → Nézd meg élőben

## 🔧 Hibakeresés

### Email nem megy ki automatikusan?

✅ Checklist:
- [ ] EmailTemplate `is_active` = TRUE?
- [ ] EmailEvent `is_active` = TRUE?
- [ ] EmailEvent esemény típusa megegyezik a kódban kiváltott event-tel?
- [ ] Címzett típus helyesen van beállítva?
- [ ] Ellenőrizd az **Email Napló**-t hiba üzenetre

### Változók nem működnek?

- Használj kapcsos zárójeleket: `{variable_name}`
- Ellenőrizd, hogy a változó elérhető-e az adott eseménynél
- Nézd meg az **Email Napló**-ban a tényleges kiküldött tartalmat

## 💡 Tippek

- **Dev módban**: Állítsd be a `MAIL_OVERRIDE_TO` változót, hogy minden email neked menjen
- **Teszt küldés**: Használd a "Teszt Email" gombot az email sablonok teszteléséhez
- **Előnézet**: Mindig nézd meg az előnézetet mentés után
- **Napló**: Rendszeresen ellenőrizd az Email Napló-t sikeres/sikertelen küldésekért
- **Mellékletek**: Max 10MB fájlméret, private storage-ban tárolva

## 🎉 Kész!

A rendszer most már használatra kész. Kezdj el email sablonokat és eseményeket létrehozni!

