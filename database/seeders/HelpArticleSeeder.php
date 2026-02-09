<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedArticles();
        $this->seedTours();
    }

    private function seedArticles(): void
    {
        $articles = [
            // === PARTNER CIKKEK ===
            [
                'title' => 'Projekt létrehozása',
                'category' => 'partner',
                'content' => "## Új projekt létrehozása\n\nA projekteket a **Projektek** menüpontban kezelheted.\n\n1. Kattints az **Új projekt** gombra\n2. Add meg a projekt nevét és az iskola adatait\n3. Állítsd be a határidőket\n4. Válaszd ki a sablont\n\nA projekt létrehozása után feltöltheted a fotókat és megoszthatod a diákokkal.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/projects', '/partner/dashboard'],
                'keywords' => ['projekt', 'létrehozás', 'új', 'iskola'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Fotók feltöltése a galériába',
                'category' => 'partner',
                'content' => "## Fotó feltöltés\n\nA projekt galériájába az alábbi módokon tölthetsz fel fotókat:\n\n1. Nyisd meg a projektet\n2. Válaszd a **Galéria** fület\n3. Húzd be a fotókat vagy kattints a **Feltöltés** gombra\n4. Várd meg amíg a feltöltés befejeződik\n\nTámogatott formátumok: JPEG, PNG, TIFF. Maximum fájlméret: 30 MB/fotó.",
                'target_roles' => ['partner', 'designer'],
                'target_plans' => [],
                'related_routes' => ['/partner/projects'],
                'keywords' => ['fotó', 'feltöltés', 'galéria', 'upload'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Galéria monitoring és export',
                'category' => 'partner',
                'content' => "## Galéria monitoring\n\nA monitoring fülön nyomon követheted a galéria állapotát:\n\n- **Saját fotók:** diákonként feltöltött saját képek\n- **Retusált fotók:** feldolgozott képek\n- **Tablóképek:** végleges tablófotók\n\n### Export lehetőségek\n- **Excel export:** részletes táblázat 3 munkalappal\n- **ZIP letöltés:** mappastruktúra diákonként, választható fájlnév stratégia",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/projects'],
                'keywords' => ['monitoring', 'export', 'excel', 'zip', 'letöltés'],
                'is_published' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'QR kód generálás és megosztás',
                'category' => 'partner',
                'content' => "## QR kódos megosztás\n\nA QR kód segítségével a diákok egyszerűen bejelentkezhetnek:\n\n1. Nyisd meg a projektet\n2. A **QR kód** szekcióban generálj kódot\n3. Nyomtasd ki vagy oszd meg digitálisan\n\nA diákok a QR kód beolvasása után közvetlenül a képválasztó felületre jutnak.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/projects'],
                'keywords' => ['qr', 'kód', 'megosztás', 'bejelentkezés'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Webshop beállítás és termékek',
                'category' => 'partner',
                'content' => "## Webshop beállítás\n\nA webshop funkció segítségével a diákok és szülők fotónyomatokat rendelhetnek:\n\n1. Menj a **Webshop** > **Beállítások** menüpontra\n2. Aktiváld a Stripe fizetést\n3. Hozz létre termékeket (papírméretek, típusok)\n4. Állítsd be az árakat\n\nA rendelések a **Webshop** > **Rendelések** oldalon kezelhetők.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/webshop/settings', '/partner/webshop/products'],
                'keywords' => ['webshop', 'nyomtatás', 'stripe', 'fizetés', 'termék'],
                'feature_key' => 'stripe_payments',
                'is_published' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'Előfizetés kezelés',
                'category' => 'partner',
                'content' => "## Előfizetési csomagok\n\nA TablóStúdió négy csomagot kínál:\n\n- **Alap:** 4 990 Ft/hó - Kezdő fotósoknak\n- **Iskola:** 14 990 Ft/hó - Legtöbb fotósnak ideális\n- **Stúdió:** 29 990 Ft/hó - Nagyobb stúdióknak\n- **VIP:** 49 990 Ft/hó - Korlátlan minden\n\nCsomagváltás és addon kezelés az **Előfizetés** menüben.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/subscription'],
                'keywords' => ['előfizetés', 'csomag', 'alap', 'iskola', 'stúdió', 'vip', 'ár'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 6,
            ],
            [
                'title' => 'Csapatkezelés és meghívás',
                'category' => 'partner',
                'content' => "## Csapattag meghívása\n\nMeghívhatsz grafikusokat, nyomdászokat és ügyintézőket:\n\n1. Menj a **Csapat** menüpontra\n2. Kattints a **Meghívás** gombra\n3. Add meg az email címet és a szerepkört\n4. A meghívott email-ben kapja a csatlakozási linket\n\nSzerepkörök: Grafikus, Nyomdász, Ügyintéző, Marketinges",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/team'],
                'keywords' => ['csapat', 'meghívás', 'grafikus', 'nyomdász', 'ügyintéző'],
                'is_published' => true,
                'sort_order' => 7,
            ],
            [
                'title' => 'Sablonok kezelése',
                'category' => 'partner',
                'content' => "## Sablon szerkesztő\n\nA sablonok segítségével egyedi tablódesignt készíthetsz:\n\n1. Menj a **Testreszabás** > **Sablonok** menüpontra\n2. Válassz egy alap sablont vagy hozz létre újat\n3. Szerkeszd a layout-ot, színeket, betűtípusokat\n\nA sablonokat projekt szinten rendelheted hozzá.",
                'target_roles' => ['partner', 'designer'],
                'target_plans' => [],
                'related_routes' => ['/partner/customization'],
                'keywords' => ['sablon', 'design', 'szerkesztő', 'layout'],
                'is_published' => true,
                'sort_order' => 8,
            ],
            [
                'title' => 'Kapcsolattartók importálása',
                'category' => 'partner',
                'content' => "## Kapcsolattartó import\n\nDiákok és szülők adatait Excel-ből importálhatod:\n\n1. Menj a **Kapcsolattartók** oldalra\n2. Kattints az **Importálás** gombra\n3. Töltsd fel az Excel fájlt (XLSX formátum)\n4. Párosítsd az oszlopokat\n5. Ellenőrizd az előnézetet, majd importálj\n\nKötelező oszlopok: Név. Opcionális: Email, Telefon.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/contacts'],
                'keywords' => ['kapcsolattartó', 'import', 'excel', 'diák', 'szülő'],
                'is_published' => true,
                'sort_order' => 9,
            ],
            [
                'title' => 'Márkajelzés testreszabás',
                'category' => 'partner',
                'content' => "## Branding beállítás\n\nSaját márkanevet, logót és faviconot jeleníthetsz meg a diákoknak látható oldalakon:\n\n1. Menj a **Testreszabás** > **Márkajelzés** oldalra\n2. Töltsd fel a logódat\n3. Állítsd be a márkanevet\n4. Mentsd el a beállításokat\n\nA diákok ezentúl a te márkanevedet látják a TablóStúdió helyett.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/customization/branding'],
                'keywords' => ['márka', 'logó', 'branding', 'testreszabás'],
                'feature_key' => 'branding',
                'is_published' => true,
                'sort_order' => 10,
            ],

            // === DIÁK/SZÜLŐ CIKKEK ===
            [
                'title' => 'Bejelentkezés QR kóddal',
                'category' => 'diak',
                'content' => "## QR kódos belépés\n\nA fotósodtól kapott QR kód segítségével tudsz bejelentkezni:\n\n1. Olvasd be a QR kódot a telefonoddal\n2. Automatikusan megnyílik a képválasztó felület\n3. Használd a 6 jegyű kódot ha nincs QR olvasód\n\nNem kell regisztrálnod, a QR kód tartalmazza az összes szükséges adatot.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/home', '/login'],
                'keywords' => ['bejelentkezés', 'qr', 'kód', 'belépés'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 20,
            ],
            [
                'title' => 'Képválasztás és finalizálás',
                'category' => 'diak',
                'content' => "## Képválasztás\n\nA képválasztás során kiválasztod a tablóra kerülő fotódat:\n\n1. Böngészd a galériát\n2. Jelöld ki a kedvenc fotóidat\n3. Válaszd ki a végleges képet\n4. Kattints a **Finalizálás** gombra\n\nA finalizálás után a fotós megkapja a választásodat és elkészíti a tablót.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/photo-selection', '/home'],
                'keywords' => ['képválasztás', 'finalizálás', 'fotó', 'tabló'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 21,
            ],
            [
                'title' => 'Webshop rendelés és fizetés',
                'category' => 'diak',
                'content' => "## Fotónyomtatás rendelés\n\nA webshopban fotónyomatokat rendelhetsz:\n\n1. Nyisd meg a webshop linket (a fotósodtól kapod)\n2. Válaszd ki a képeket és a nyomtatási méreteket\n3. Add meg a szállítási adatokat\n4. Fizess bankkártyával (Stripe)\n\nA rendelés elkészültéről email értesítést kapsz.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/shop'],
                'keywords' => ['webshop', 'rendelés', 'nyomtatás', 'fizetés', 'kártya'],
                'is_published' => true,
                'sort_order' => 22,
            ],
            [
                'title' => 'Fórum használata',
                'category' => 'diak',
                'content' => "## Fórum\n\nA fórumon beszélgethetsz az osztálytársaiddal:\n\n1. Nyisd meg a **Fórum** menüpontot\n2. Böngészd a meglévő témákat vagy hozz létre újat\n3. Írj hozzászólást, reagálj mások bejegyzéseire\n\nA fórum csak az osztályodnak szól, mások nem láthatják.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/forum'],
                'keywords' => ['fórum', 'beszélgetés', 'hozzászólás', 'osztály'],
                'feature_key' => 'forum',
                'is_published' => true,
                'sort_order' => 23,
            ],
            [
                'title' => 'Szavazás',
                'category' => 'diak',
                'content' => "## Szavazás\n\nAz osztály szavazásokat indíthat különböző témákban:\n\n1. Nyisd meg a **Szavazás** menüpontot\n2. Válaszd ki a szavazást\n3. Add le a szavazatodat\n\nAz eredmények valós időben frissülnek.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/voting'],
                'keywords' => ['szavazás', 'poll', 'szavazat'],
                'feature_key' => 'polls',
                'is_published' => true,
                'sort_order' => 24,
            ],
            [
                'title' => 'Pontok, jelvények és rangsor',
                'category' => 'diak',
                'content' => "## Gamification\n\nAktivitásodért pontokat és jelvényeket kapsz:\n\n- **Képválasztás:** pont a kiválasztásért\n- **Fórum hozzászólás:** pont az aktivitásért\n- **Szavazás:** pont a részvételért\n- **Jelvények:** különleges teljesítményekért\n\nA rangsoron láthatod, hol állsz az osztálytársaid között.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/home'],
                'keywords' => ['pont', 'jelvény', 'rangsor', 'gamification'],
                'is_published' => true,
                'sort_order' => 25,
            ],

            // === CROSS-ROLE / HELPDESK CIKKEK ===
            [
                'title' => 'Stripe beállítás fotósoknak',
                'category' => 'partner',
                'content' => "## Stripe fizetés beállítás\n\nA Stripe integrációval online fizetést fogadhatsz:\n\n1. Menj a **Webshop** > **Beállítások** oldalra\n2. Kattints a **Stripe összekapcsolás** gombra\n3. Kövesd a Stripe regisztrációs folyamatot\n4. Aktiválás után a vásárlók bankkártyával fizethetnek\n\nA bevétel közvetlenül a Stripe fiókodra érkezik.",
                'target_roles' => ['partner'],
                'target_plans' => ['iskola', 'studio', 'vip'],
                'related_routes' => ['/partner/webshop/settings'],
                'keywords' => ['stripe', 'fizetés', 'kártya', 'online', 'beállítás'],
                'feature_key' => 'stripe_payments',
                'is_published' => true,
                'sort_order' => 30,
            ],
            [
                'title' => 'Iskolák kezelése',
                'category' => 'partner',
                'content' => "## Iskolák\n\nAz iskolák kezelése a **Iskolák** menüpontban:\n\n1. Hozz létre új iskolát\n2. Add meg a nevét, címét\n3. Rendeld hozzá a projektekhez\n\nAz iskolák segítenek a projektek csoportosításában és a statisztikák követésében.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/schools'],
                'keywords' => ['iskola', 'kezelés', 'létrehozás'],
                'is_published' => true,
                'sort_order' => 31,
            ],
            [
                'title' => 'Tanári adatbázis',
                'category' => 'partner',
                'content' => "## Tanári adatbázis\n\nAz AI-alapú tanári adatbázis segít a visszatérő tanárok felismerésében:\n\n1. Menj a **Tanárok** menüpontra\n2. Böngészd vagy keress tanárokat\n3. Az AI automatikusan párosítja a neveket a korábbi projektekből\n\nEz segít a kapcsolatok nyomon követésében és a marketing célzásban.",
                'target_roles' => ['partner'],
                'target_plans' => [],
                'related_routes' => ['/partner/teachers'],
                'keywords' => ['tanár', 'adatbázis', 'AI', 'párosítás'],
                'is_published' => true,
                'sort_order' => 32,
            ],

            // === FAQ CIKKEK (mindenkinek) ===
            [
                'title' => 'Mi az a TablóStúdió?',
                'category' => 'altalanos',
                'content' => "## A TablóStúdió platformról\n\nA TablóStúdió egy online tablófotós platform, ahol:\n\n- **Fotósok** kezelik a projekteket, feltöltik és szerkesztik a fotókat\n- **Diákok és szülők** kiválasztják a tablóra kerülő képet\n- **Webshop** segítségével fotónyomatokat rendelhetnek\n- **Közösségi funkciók** segítik az osztály kommunikációját\n\nA platform célja, hogy a tablófotózás folyamatát egyszerűbbé és élvezetesebbé tegye.",
                'target_roles' => [],
                'target_plans' => [],
                'related_routes' => [],
                'keywords' => ['tablóstúdió', 'platform', 'mi ez', 'bemutatkozás'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 50,
            ],
            [
                'title' => 'Hogyan léphetek kapcsolatba a fotósommal?',
                'category' => 'diak',
                'content' => "## Kapcsolatfelvétel\n\nA fotósoddal az alábbi módokon veheted fel a kapcsolatot:\n\n1. A felső sávban található **Kapcsolat** gombbal\n2. A projekt oldalán lévő elérhetőségeken\n3. Az iskolád által megadott kapcsolattartón keresztül\n\nHa technikai problémád van, használd a platform beépített segítséget.",
                'target_roles' => ['guest'],
                'target_plans' => [],
                'related_routes' => ['/home'],
                'keywords' => ['kapcsolat', 'fotós', 'segítség', 'probléma'],
                'is_published' => true,
                'is_faq' => true,
                'sort_order' => 51,
            ],
        ];

        foreach ($articles as $articleData) {
            HelpArticle::updateOrCreate(
                ['title' => $articleData['title']],
                $articleData
            );
        }
    }

    private function seedTours(): void
    {
        $tours = [
            [
                'key' => 'partner-first-project',
                'title' => 'Első projekt létrehozása',
                'trigger_route' => '/partner/dashboard',
                'target_roles' => ['partner'],
                'target_plans' => [],
                'trigger_type' => 'first_visit',
                'steps' => [
                    ['title' => 'Üdvözlünk!', 'content' => 'Ez a partneri vezérlőpultod. Innen kezelheted az összes projektedet, diákjaidat és beállításaidat.', 'target_selector' => '.content', 'placement' => 'bottom'],
                    ['title' => 'Projektek', 'content' => 'A Projektek menüpontban hozhatod létre és kezelheted a tablóprojektjeidet.', 'target_selector' => '[routerlink*="projects"]', 'placement' => 'right'],
                    ['title' => 'Új projekt', 'content' => 'Kattints ide az első projekted létrehozásához!', 'target_selector' => '[routerlink*="projects/new"]', 'placement' => 'right'],
                ],
            ],
            [
                'key' => 'partner-gallery-upload',
                'title' => 'Fotók feltöltése',
                'trigger_route' => '/partner/projects',
                'target_roles' => ['partner', 'designer'],
                'target_plans' => [],
                'trigger_type' => 'first_visit',
                'steps' => [
                    ['title' => 'Projekt galéria', 'content' => 'Nyisd meg a projektet és válaszd a Galéria fület a fotók kezeléséhez.', 'target_selector' => null, 'placement' => 'bottom'],
                    ['title' => 'Feltöltés', 'content' => 'Húzd be a fotókat vagy kattints a Feltöltés gombra. Támogatott: JPEG, PNG, TIFF.', 'target_selector' => null, 'placement' => 'bottom'],
                ],
            ],
            [
                'key' => 'client-photo-selection',
                'title' => 'Képválasztás',
                'trigger_route' => '/photo-selection',
                'target_roles' => ['guest'],
                'target_plans' => [],
                'trigger_type' => 'first_visit',
                'steps' => [
                    ['title' => 'Képválasztás', 'content' => 'Itt tudod kiválasztani a tablóra kerülő fotódat. Böngészd a galériát!', 'target_selector' => null, 'placement' => 'bottom'],
                    ['title' => 'Kijelölés', 'content' => 'Kattints a kedvenc képeidre, majd válaszd ki a véglegeset.', 'target_selector' => null, 'placement' => 'bottom'],
                    ['title' => 'Finalizálás', 'content' => 'Ha kiválasztottad a képet, kattints a Finalizálás gombra. Ezután a fotós megkapja a választásodat.', 'target_selector' => null, 'placement' => 'bottom'],
                ],
            ],
            [
                'key' => 'client-webshop-intro',
                'title' => 'Webshop bemutató',
                'trigger_route' => '/shop',
                'target_roles' => ['guest'],
                'target_plans' => [],
                'trigger_type' => 'first_visit',
                'steps' => [
                    ['title' => 'Webshop', 'content' => 'Itt rendelhetsz fotónyomatokat a tablófotóidból!', 'target_selector' => null, 'placement' => 'bottom'],
                    ['title' => 'Termékválasztás', 'content' => 'Válaszd ki a kívánt méretet és papírtípust, majd add a kosárba.', 'target_selector' => null, 'placement' => 'bottom'],
                ],
            ],
        ];

        foreach ($tours as $tourData) {
            $steps = $tourData['steps'];
            unset($tourData['steps']);

            $tour = HelpTour::updateOrCreate(
                ['key' => $tourData['key']],
                $tourData
            );

            $tour->steps()->delete();
            foreach ($steps as $index => $stepData) {
                HelpTourStep::create([
                    'help_tour_id' => $tour->id,
                    'step_number' => $index + 1,
                    'title' => $stepData['title'],
                    'content' => $stepData['content'],
                    'target_selector' => $stepData['target_selector'],
                    'placement' => $stepData['placement'] ?? 'bottom',
                ]);
            }
        }
    }
}
