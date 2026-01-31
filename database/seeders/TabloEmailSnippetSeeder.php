<?php

namespace Database\Seeders;

use App\Models\TabloEmailSnippet;
use Illuminate\Database\Seeder;

class TabloEmailSnippetSeeder extends Seeder
{
    public function run(): void
    {
        $snippets = [
            [
                'name' => 'Minta elkészült',
                'slug' => 'minta-elkeszult',
                'subject' => 'Elkészült a tabló minta - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Elkészítettem a tabló mintát a {iskola} {osztaly} osztálya részére.</p><p>A mintát a mellékletben találja. Kérem, nézze át és jelezze vissza, ha bármilyen módosítást szeretne.</p><p>Ha minden rendben, kérem erősítse meg, hogy elkezdhetem a véglegesítést.</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Módosítás elkészült',
                'slug' => 'modositas-elkeszult',
                'subject' => 'Módosított tabló minta - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Elkészítettem a kért módosításokat a {osztaly} tablóján.</p><p>A frissített mintát a mellékletben találja. Kérem, ellenőrizze, hogy minden megfelelő-e.</p><p>Várom visszajelzését!</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Hiányzó fotó kérés',
                'slug' => 'hianyzo-foto-keres',
                'subject' => 'Hiányzó fotók - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>A {iskola} {osztaly} osztályának tablójához még hiányoznak fotók.</p><p>Kérem, küldje el a hiányzó képeket minél hamarabb, hogy be tudjam fejezni a tablót.</p><p>Ha bármilyen kérdése van a fotók méretével vagy formátumával kapcsolatban, szívesen segítek.</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Határidő emlékeztető',
                'slug' => 'hatarido-emlekezteto',
                'subject' => 'Emlékeztető: Tabló véglegesítés - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Szeretném emlékeztetni, hogy a {iskola} {osztaly} osztályának tablója még várakozik a jóváhagyásra.</p><p>Kérem, jelezze vissza minél hamarabb, hogy el tudjam készíteni a végleges verziót.</p><p>Ha bármilyen kérdése van, állok rendelkezésére.</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 4,
                'is_active' => true,
                'is_auto_enabled' => true,
                'auto_trigger' => 'no_reply_days',
                'auto_trigger_config' => ['days' => 7],
            ],
            [
                'name' => 'Véglegesítés visszaigazolás',
                'slug' => 'veglegesites-visszaigazolas',
                'subject' => 'Tabló véglegesítve - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Örömmel értesítem, hogy a {iskola} {osztaly} osztályának tablója elkészült és nyomdába került!</p><p>A tablók várhatóan 2-3 héten belül érkeznek.</p><p>Köszönöm a közös munkát!</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Adatok egyeztetése',
                'slug' => 'adatok-egyeztetese',
                'subject' => 'Kérdés a tabló adatokkal kapcsolatban - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>A {iskola} {osztaly} osztályának tablójával kapcsolatban szeretnék egyeztetni néhány adatot.</p><p>Kérem, erősítse meg a következőket:</p><ul><li>A nevek helyesen szerepelnek-e?</li><li>A fotók sorrendje megfelelő-e?</li></ul><p>Várom válaszát!</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Köszönetnyilvánítás',
                'slug' => 'koszonetnyilvanitas',
                'subject' => 'Köszönjük a megrendelést! - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Köszönjük, hogy a Tablókirályt választotta a {iskola} {osztaly} osztályának tablójához!</p><p>Hamarosan megkezdem a munkát és küldöm a mintát.</p><p>Ha bármilyen kérdése van, bátran keressen!</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Fizetési emlékeztető',
                'slug' => 'fizetesi-emlekezteto',
                'subject' => 'Fizetési emlékeztető - {osztaly}',
                'content' => '<p>Kedves {nev}!</p><p>Szeretném jelezni, hogy a {iskola} {osztaly} osztályának tablójához tartozó számla még nincs kiegyenlítve.</p><p>Kérem, intézze a befizetést, hogy el tudjam készíteni a végleges tablókat.</p><p>Ha már megtörtént a fizetés, kérem tekintse tárgytalannak ezt az üzenetet.</p><p>Üdvözlettel,<br>Tablókirály</p>',
                'sort_order' => 8,
                'is_active' => true,
                'is_auto_enabled' => false,
                'auto_trigger' => 'no_reply_days',
                'auto_trigger_config' => ['days' => 14],
            ],
            [
                'name' => 'Árajánlat küldése',
                'slug' => 'arajanlat-kuldese',
                'subject' => 'Árajánlat ({quote_number}) - tablokiraly.hu',
                'content' => '<p>Tisztelt {nev}!</p>

<p>Köszönjük érdeklődését! Mellékletben küldöm az árajánlatot.</p>

<p>Az árajánlat 30 napig érvényes. Ha bármilyen kérdése van, vagy szeretne egyedi igényeket egyeztetni, kérem keressen bizalommal!</p>

<p>--<br>
Üdvözlettel:<br>
<strong>Nové Ferenc</strong><br>
Ügyvezető<br>
tablokiraly.hu</p>',
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($snippets as $snippet) {
            TabloEmailSnippet::updateOrCreate(
                ['slug' => $snippet['slug']],
                $snippet
            );
        }
    }
}
