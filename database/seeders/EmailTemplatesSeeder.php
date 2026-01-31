<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ==========================================
            // AUTH & USER MANAGEMENT
            // ==========================================
            [
                'name' => 'welcome_email',
                'subject' => 'Üdvözlünk a {site_name} oldalon!',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Örülünk, hogy csatlakoztál hozzánk a <strong>{site_name}</strong> oldalon!</p>

<p>Az alábbi adatokkal regisztráltál:</p>
<ul>
  <li><strong>Név:</strong> {user_name}</li>
  <li><strong>Email:</strong> {user_email}</li>
  <li><strong>Osztály:</strong> {user_class}</li>
</ul>

<p>Hamarosan elküldjük számodra a fotóid megtekintéséhez szükséges linket!</p>

<p>Ha bármilyen kérdésed van, ne habozz felvenni velünk a kapcsolatot!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'password_reset',
                'subject' => 'Jelszó visszaállítás kérés - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Jelszó visszaállítási kérést kaptunk a fiókodhoz.</p>

<p>Kattints az alábbi linkre a jelszó visszaállításához:</p>

<p><a href="{reset_link}" style="display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Jelszó Visszaállítása</a></p>

<p>Ha nem te kérted a jelszó visszaállítást, hagyd figyelmen kívül ezt az emailt.</p>

<p><strong>Figyelem:</strong> Ez a link 60 percig érvényes.</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'user_created_credentials',
                'subject' => 'Fiókod létrehozva - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Örömmel értesítünk, hogy létrehoztuk a fiókodat a <strong>{site_name}</strong> oldalon!</p>

<h3>Bejelentkezési adataid:</h3>
<ul>
  <li><strong>Email:</strong> {user_email}</li>
  <li><strong>Jelszó:</strong> <code style="background-color: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">{password}</code></li>
</ul>

<p><a href="{site_url}/auth/login" style="display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Bejelentkezés Most</a></p>

<p><strong>Fontos:</strong> Első bejelentkezés után javasoljuk, hogy változtasd meg a jelszavadat!</p>

<p>Ha bármilyen kérdésed van, keress minket bizalommal!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'registration_welcome',
                'subject' => 'Sikeres regisztráció - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Köszönjük, hogy regisztráltál a <strong>{site_name}</strong> oldalon!</p>

<p>A regisztrációd sikeresen megtörtént. Most már bejelentkezhetsz és böngészheted a fotóidat.</p>

<p><a href="{site_url}/auth/login" style="display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Bejelentkezés</a></p>

<p>Ha bármilyen kérdésed van, ne habozz felvenni velünk a kapcsolatot!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'password_changed',
                'subject' => 'Jelszó megváltoztatva - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Ez egy megerősítő értesítés, hogy a jelszavad megváltozott a <strong>{site_name}</strong> fiókodon.</p>

<p><strong>Időpont:</strong> {current_date}</p>

<p>Ha nem te változtattad meg a jelszavadat, azonnal lépj kapcsolatba velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'user_magic_login',
                'subject' => 'Magic Link belépés - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Kértél egy magic linket a gyors bejelentkezéshez a <strong>{site_name}</strong> oldalon.</p>

<p style="text-align: center; margin: 30px 0;">
  <a href="{magic_link}" style="display: inline-block; padding: 16px 32px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px;">
    ✨ Bejelentkezés Magic Linkkel
  </a>
</p>

<p><strong>Fontos információk:</strong></p>
<ul>
  <li>Ez a link 24 órán keresztül érvényes</li>
  <li>A linkre kattintva automatikusan be leszel jelentkezve</li>
  <li>Első bejelentkezéskor kérünk, állíts be egy új jelszót</li>
</ul>

{digit_code_section}

{quick_link_section}

<p><strong>Biztonsági megjegyzés:</strong> Ha nem te kérted ezt a linket, hagyd figyelmen kívül ezt az emailt.</p>

<p>Ha bármilyen kérdésed van, ne habozz kapcsolatba lépni velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],

            // ==========================================
            // WORK SESSION
            // ==========================================
            [
                'name' => 'work_session_invite',
                'subject' => 'Meghívás munkamenetre: {work_session_name} - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Meghívást kaptál a <strong>{work_session_name}</strong> munkamenetre!</p>

<p>A munkamenethez való csatlakozáshoz használd az alábbi belépési kódot:</p>

<div style="text-align: center; background-color: #dcfce7; padding: 32px; border-radius: 12px; margin: 32px 0; border: 2px solid #16a34a;">
  <p style="font-size: 14px; color: #15803d; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">Megosztható belépési kód</p>
  <h1 style="font-size: 48px; letter-spacing: 12px; color: #16a34a; margin: 16px 0; font-weight: bold;">
    {digit_code}
  </h1>
</div>

<h3>Hogyan léphetsz be?</h3>
<ol>
  <li>Látogass el a <a href="{site_url}/auth/verify" style="color: #16a34a;">{site_url}/auth/verify</a> oldalra</li>
  <li>Írd be a fenti <strong>6 jegyű kódot</strong></li>
  <li>Kattints a "Belépés" gombra</li>
</ol>

<div style="margin: 24px 0; padding: 16px; background-color: #f0fdf4; border-radius: 8px; border-left: 4px solid #16a34a;">
  <p style="margin: 0 0 8px 0; color: #15803d; font-size: 14px; font-weight: 600;">
    Gyors belépés ezzel a linkkel:
  </p>
  <p style="margin: 0; font-size: 13px; color: #166534;">
    Ez a link automatikusan kitölti a belépési kódot a weboldalon:
  </p>
  <p style="margin: 12px 0 0 0;">
    <a href="{site_url}/auth/verify?code={digit_code}&focus=true" style="color: #16a34a; word-break: break-all; font-size: 13px; font-weight: 500;">
      {site_url}/auth/verify?code={digit_code}
    </a>
  </p>
</div>

<p><strong>Fontos információk:</strong></p>
<ul>
  <li>A kód megosztható a munkamenet résztvevőivel</li>
  <li>Első bejelentkezéskor regisztrálhatsz a rendszerbe</li>
  <li>A kód egy munkamenethez kapcsolódik</li>
</ul>

<p>Ha bármilyen kérdésed van, ne habozz kapcsolatba lépni velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'work_session_access_code',
                'subject' => 'Belépési kódod - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>A fotóidhoz való hozzáféréshez itt a belépési kódod:</p>

<h3 style="text-align: center; font-size: 36px; letter-spacing: 8px; color: #4F46E5; margin: 24px 0;">
  <strong>{access_code}</strong>
</h3>

<p style="text-align: center;">vagy kattints az alábbi linkre a gyors belépéshez:</p>

<p style="text-align: center;"><a href="{work_session_url}" style="display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Belépés Most</a></p>

<p><strong>Érvényesség:</strong> {expires_at}</p>

<p>A kód segítségével megtekintheted és kiválaszthatod a fotóidat.</p>

<p>Jó böngészést!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'work_session_reminder',
                'subject' => 'Emlékeztető: Belépési kódod hamarosan lejár',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Ez egy emlékeztető, hogy a belépési kódod hamarosan lejár.</p>

<p><strong>Belépési kód:</strong> <code style="background-color: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 20px; letter-spacing: 4px;">{access_code}</code></p>

<p><strong>Lejárat:</strong> {expires_at}</p>

<p>Ne feledd: a lejárat után nem tudsz hozzáférni a fotóidhoz ezzel a kóddal!</p>

<p><a href="{work_session_url}" style="display: inline-block; padding: 12px 24px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold;">Belépés Most</a></p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => false, // Kezdetben inaktív
                'priority' => 'normal',
            ],

            // ==========================================
            // WEBSHOP - ORDERS
            // ==========================================
            [
                'name' => 'order_confirmation',
                'subject' => 'Megrendelésed visszaigazolása - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Köszönjük a megrendelésedet!</p>

<h3>Megrendelés részletei:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Végösszeg:</strong> {order_total}</li>
  <li><strong>Tételek száma:</strong> {order_items_count}</li>
  <li><strong>Státusz:</strong> {order_status}</li>
  <li><strong>Fizetési mód:</strong> {payment_method}</li>
  <li><strong>Szállítási mód:</strong> {shipping_method}</li>
</ul>

<p>Hamarosan feldolgozzuk a megrendelésedet és értesítünk a továbbiakról!</p>

<p>Kérdés esetén keress minket bizalommal!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'order_payment_pending',
                'subject' => 'Fizetésre vár - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>A megrendelésed elkészült, de a fizetés még függőben van.</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Végösszeg:</strong> {grand_total}</li>
  <li><strong>Fizetési mód:</strong> {payment_method}</li>
</ul>

<p>Kérjük, ha még nem tetted meg, végezd el a fizetést a megrendelésed feldolgozásához!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_payment_received',
                'subject' => 'Fizetés beérkezett - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Örömmel értesítünk, hogy a fizetésed beérkezett!</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Végösszeg:</strong> {grand_total}</li>
  <li><strong>Fizetési mód:</strong> {payment_method}</li>
</ul>

<p>A megrendelésedet most feldolgozzuk és hamarosan gyártásba kerül!</p>

<p>Értesítünk a folyamat minden lépéséről.</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_payment_failed',
                'subject' => 'Fizetés sikertelen - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Sajnálattal értesítünk, hogy a fizetésed nem sikerült.</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Végösszeg:</strong> {grand_total}</li>
  <li><strong>Fizetési mód:</strong> {payment_method}</li>
</ul>

<p>Kérjük, próbáld meg újra, vagy válassz másik fizetési módot!</p>

<p>Ha segítségre van szükséged, lépj kapcsolatba velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_in_production',
                'subject' => 'Gyártás alatt - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Jó hírünk van! A megrendelésed gyártás alatt van!</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Tételek száma:</strong> {order_items_count}</li>
</ul>

<p>Hamarosan elkészülnek a fotóid és értesítünk, amikor elküldésre kerülnek!</p>

<p>Köszönjük a türelmedet!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_shipped',
                'subject' => 'Csomagod elküldve - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Jó hírünk van! A megrendelésedet elküldtük!</p>

<h3>Szállítási információk:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Szállítási mód:</strong> {shipping_method}</li>
  <li><strong>Követő szám:</strong> {tracking_number}</li>
  <li><strong>Cím:</strong> {shipping_address}</li>
</ul>

<p>A csomagot várhatóan a következő napokban kézhez kapod!</p>

<p>Jó böngészést és élvezd a fotóidat!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_delivered',
                'subject' => 'Megrendelésed kézbesítve - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Örömmel értesítünk, hogy a megrendelésed kézbesítésre került!</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Kézbesítési dátum:</strong> {current_date}</li>
</ul>

<p>Reméljük, elégedett vagy a fotóiddal!</p>

<p>Ha bármilyen problémád van, kérjük, jelezd felénk!</p>

<p>Köszönjük, hogy minket választottál!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_cancelled',
                'subject' => 'Megrendelésed törölve - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Értesítünk, hogy a megrendelésed törölve lett.</p>

<h3>Megrendelés adatok:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Törlés dátuma:</strong> {current_date}</li>
</ul>

<p>Ha fizetés történt, a visszatérítést hamarosan feldolgozzuk.</p>

<p>Ha kérdésed van, lépj kapcsolatba velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            // ==========================================
            // ALBUMS & PHOTOS
            // ==========================================
            [
                'name' => 'album_created_notification',
                'subject' => 'Új fotózás elérhető: {album_title}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Örömmel értesítünk, hogy egy új fotózás került fel az oldalra!</p>

<h3>Fotózás részletei:</h3>
<ul>
  <li><strong>Album neve:</strong> {album_title}</li>
  <li><strong>Osztály:</strong> {album_class}</li>
  <li><strong>Képek száma:</strong> {album_photo_count} db</li>
</ul>

<p>Látogass el az oldalra és válaszd ki a kedvenc fotóidat!</p>

<p>Jó böngészést kívánunk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'order_status_changed',
                'subject' => 'Megrendelésed státusza megváltozott - #{order_number}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>A megrendelésed státusza megváltozott!</p>

<h3>Státusz információk:</h3>
<ul>
  <li><strong>Megrendelés szám:</strong> #{order_number}</li>
  <li><strong>Új státusz:</strong> {order_status}</li>
</ul>

<p>Köszönjük a türelmedet!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],

            // ==========================================
            // GENERAL
            // ==========================================
            [
                'name' => 'manual_notification',
                'subject' => 'Értesítés - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Ez egy egyedi értesítő üzenet a <strong>{site_name}</strong> rendszerből.</p>

<p>Ha kérdésed van, keress minket bizalommal!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],
            [
                'name' => 'test_email',
                'subject' => 'Teszt Email - {current_date}',
                'body' => '<h2>Teszt Email</h2>

<p>Ez egy teszt email az SMTP rendszer tesztelésére.</p>

<h3>Teszt Információk:</h3>
<ul>
  <li><strong>Oldal neve:</strong> {site_name}</li>
  <li><strong>Küldés dátuma:</strong> {current_date}</li>
  <li><strong>Aktuális év:</strong> {current_year}</li>
</ul>

<p>Ha ezt az emailt látod, az SMTP rendszer megfelelően működik!</p>

<p><a href="{site_url}">Látogass el az oldalra</a></p>

<p>Üdvözlettel,<br>
Az Email Rendszer</p>',
                'is_active' => true,
                'priority' => 'normal',
            ],

            // ==========================================
            // TABLO WORKFLOW
            // ==========================================
            [
                'name' => 'tablo_user_registered',
                'subject' => 'Sikeres regisztráció tablózás közben - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p>Köszönjük, hogy regisztráltál a <strong>{site_name}</strong> oldalon tablókép kiválasztás közben!</p>

<p>A regisztrációd sikeresen megtörtént a <strong>{parent_session_name}</strong> munkamenethez kapcsolódóan.</p>

<h3>Gyors belépés:</h3>
<p style="text-align: center; margin: 30px 0;">
  <a href="{magic_link}" style="display: inline-block; padding: 16px 32px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px;">
    ✨ Bejelentkezés Magic Linkkel
  </a>
</p>

<p><strong>Fontos információk:</strong></p>
<ul>
  <li>Ez a link automatikus bejelentkezést biztosít</li>
  <li>A linkre kattintva folytathatod a tablókép kiválasztást</li>
  <li>Első bejelentkezéskor ajánljuk, hogy állíts be saját jelszót a profil beállításokban</li>
</ul>

<div style="margin: 24px 0; padding: 16px; background-color: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
  <p style="margin: 0; color: #92400e; font-size: 14px;">
    <strong>⚠️ Biztonsági megjegyzés:</strong> A magic link csak egyszer használható. Ha többször szeretnél bejelentkezni, állíts be jelszót a profilodban!
  </p>
</div>

<p>Ha bármilyen kérdésed van, ne habozz kapcsolatba lépni velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
            [
                'name' => 'tablo_workflow_completed',
                'subject' => 'Gratulálunk! Sikeresen kiválasztottad a tablóképedet - {site_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p style="font-size: 18px; color: #16a34a; font-weight: bold;">🎉 Gratulálunk! Sikeresen kiválasztottad a tablóképedet!</p>

<p>Örömmel értesítünk, hogy a tablókép kiválasztási folyamat sikeresen befejeződött!</p>

<div style="text-align: center; margin: 30px 0;">
  <img src="{tablo_photo_thumb_url}" alt="Kiválasztott tablókép" style="max-width: 300px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
</div>

<h3>Munkamenet információk:</h3>
<ul>
  <li><strong>Munkamenet neve:</strong> {work_session_name}</li>
  <li><strong>Véglegesítés dátuma:</strong> {completion_date}</li>
</ul>

<p style="text-align: center; margin: 30px 0;">
  <a href="{magic_link_worksession}" style="display: inline-block; padding: 16px 32px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px;">
    📸 Nézd meg a képeidet
  </a>
</p>

<p><strong>Fontos információk:</strong></p>
<ul>
  <li>Ez a link 6 hónapon keresztül érvényes</li>
  <li>A linkre kattintva automatikusan be leszel jelentkezve</li>
  <li>Megtekintheted és megrendelheted a fotóidat</li>
  <li>Bármikor visszatérhetsz erre a linkre</li>
</ul>

<div style="margin: 24px 0; padding: 16px; background-color: #f0fdf4; border-radius: 8px; border-left: 4px solid #16a34a;">
  <p style="margin: 0; color: #15803d; font-size: 14px;">
    <strong>💡 Tipp:</strong> Mentsd el ezt az emailt vagy a linket, hogy később is könnyen hozzáférhess a képeidhez!
  </p>
</div>

<p>Ha bármilyen kérdésed van, ne habozz kapcsolatba lépni velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],

            // ==========================================
            // ZIP DOWNLOADS
            // ==========================================
            [
                'name' => 'zip_ready',
                'subject' => 'ZIP fájl elkészült - {work_session_name}',
                'body' => '<h2>Kedves {user_name}!</h2>

<p style="font-size: 18px; color: #16a34a; font-weight: bold;">✅ A kért ZIP fájl elkészült és letölthető!</p>

<h3>Munkamenet információk:</h3>
<ul>
  <li><strong>Munkamenet neve:</strong> {work_session_name}</li>
  <li><strong>Fájlnév:</strong> {filename}</li>
</ul>

<p style="text-align: center; margin: 30px 0;">
  <a href="{download_url}" style="display: inline-block; padding: 16px 32px; background-color: #4F46E5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 18px;">
    📥 ZIP Letöltése
  </a>
</p>

<div style="margin: 24px 0; padding: 16px; background-color: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
  <p style="margin: 0; color: #92400e; font-size: 14px;">
    <strong>⚠️ Fontos:</strong> A letöltési link 24 órán keresztül érvényes. A ZIP fájl automatikusan törlődik 24 óra után.
  </p>
</div>

<p>Ha bármilyen problémád van a letöltéssel, ne habozz kapcsolatba lépni velünk!</p>

<p>Üdvözlettel,<br>
A {site_name} Csapata</p>',
                'is_active' => true,
                'priority' => 'high',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }

        $this->command->info('Email sablonok sikeresen létrehozva!');
    }
}
