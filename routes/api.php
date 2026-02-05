<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Route fájlok szervezése:
| - auth.php: Bejelentkezés, regisztráció, jelszókezelés, 2FA
| - public.php: Nyilvános route-ok (health, pricing, cart, orders, photos, client)
| - partner.php: Partner dashboard, projektek, iskolák, csapatkezelés, előfizetés
| - marketer.php: Ügyintéző/marketinges route-ok
| - admin.php: Super admin rendszer adminisztráció
| - tablo.php: Tabló Management API + Tabló Frontend route-ok
|
*/

require __DIR__.'/api/auth.php';
require __DIR__.'/api/public.php';
require __DIR__.'/api/partner.php';
require __DIR__.'/api/marketer.php';
require __DIR__.'/api/admin.php';
require __DIR__.'/api/tablo.php';
