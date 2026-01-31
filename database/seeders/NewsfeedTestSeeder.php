<?php

namespace Database\Seeders;

use App\Models\TabloNewsfeedComment;
use App\Models\TabloNewsfeedLike;
use App\Models\TabloNewsfeedPost;
use Illuminate\Database\Seeder;

class NewsfeedTestSeeder extends Seeder
{
    private int $projectId = 6;

    private int $guestId = 18; // Akos

    private array $emojis = ['❤️', '💀', '😭', '🫡', '👀'];

    public function run(): void
    {
        $this->command->info('Creating test newsfeed posts...');

        // Clear existing posts for this project (except first few)
        TabloNewsfeedPost::where('tablo_project_id', $this->projectId)
            ->where('id', '>', 15)
            ->forceDelete();

        // Create announcements
        $announcements = $this->createAnnouncements();

        // Create events
        $events = $this->createEvents();

        $this->command->info('Created '.count($announcements).' announcements and '.count($events).' events.');
    }

    private function createAnnouncements(): array
    {
        $posts = [
            [
                'title' => 'Üdvözlünk a tablón!',
                'content' => "Kedves Diákok és Szülők!\n\nÖrömmel üdvözlünk benneteket a tablókészítés közös platformján. Itt fogtok értesülni minden fontos információról a tablókészítés folyamatáról.\n\nKérünk, hogy rendszeresen nézzétek az oldalt, mert itt tesszük közzé a fotózási időpontokat, a sablonválasztási lehetőségeket és minden egyéb fontos tudnivalót.\n\nHa bármilyen kérdésetek van, nyugodtan írjatok kommentet!",
                'comments' => 8,
                'reactions' => ['❤️' => 12, '🫡' => 3],
            ],
            [
                'title' => 'Fotózási időpontok',
                'content' => "A fotózás időpontjai a következők:\n\n📅 Április 15. (hétfő): 9:00-12:00\n📅 Április 16. (kedd): 13:00-16:00\n\nKérjük mindenki legyen ott időben! A pontos beosztást az osztályfőnök fogja kiküldeni.",
                'comments' => 5,
                'reactions' => ['❤️' => 8],
            ],
            [
                'title' => 'Fontos határidők',
                'content' => 'Kérjük tartsátok be az alábbi határidőket, hogy a tabló időben elkészülhessen!',
                'comments' => 15,
                'reactions' => ['😭' => 5, '❤️' => 10],
            ],
            [
                'title' => 'Új sablonok érkeztek!',
                'content' => 'Felkerültek az új tabló sablonok! Nézzétek meg és válasszátok ki a nektek tetszőt. Több stílus közül választhattok: klasszikus, modern, minimalista.',
                'comments' => 3,
                'reactions' => ['❤️' => 4],
            ],
            [
                'title' => 'Árazási információk',
                'content' => "A tablók árazása a következőképpen alakul:\n\n💰 Alap csomag: 15.000 Ft/fő\n- 1 db A3-as tabló\n- 1 db egyéni fotó\n\n💎 Prémium csomag: 25.000 Ft/fő\n- 1 db A2-es tabló\n- 3 db egyéni fotó\n- Digitális másolat\n\nKérdések esetén írjatok!",
                'comments' => 12,
                'reactions' => ['👀' => 6, '❤️' => 3],
            ],
            [
                'title' => 'Kérdések és válaszok',
                'content' => 'Itt gyűjtjük össze a leggyakrabban feltett kérdéseket. Kérdezzetek bátran a kommentekben!',
                'comments' => 20,
                'reactions' => ['❤️' => 15],
            ],
            [
                'title' => 'Próbanyomat kész! 🎉',
                'content' => 'Elkészült a próbanyomat! Hamarosan láthatjátok!',
                'comments' => 2,
                'reactions' => ['❤️' => 25, '💀' => 2],
            ],
            [
                'title' => 'Fizetési emlékeztető',
                'content' => 'Kérjük a fizetési határidő betartását! A befizetés módja: átutalás vagy készpénz az osztálypénztárosnál.',
                'comments' => 0,
                'reactions' => ['😭' => 8],
            ],
            [
                'title' => 'Csoportkép helyszín',
                'content' => 'A csoportkép az iskola aulájában készül.',
                'comments' => 7,
                'reactions' => ['❤️' => 6],
            ],
            [
                'title' => 'Névsor véglegesítése',
                'content' => 'A névsor véglegesítésre került. Ha hibát találtok, jelezzétek az osztályfőnöknek!',
                'comments' => 4,
                'reactions' => ['🫡' => 10],
            ],
            [
                'title' => 'Grafikusunk bemutatkozik',
                'content' => "Sziasztok! Én vagyok a tablótok grafikus tervezője. 10 éve foglalkozom tablókészítéssel, és nagyon örülök, hogy együtt dolgozhatok veletek!\n\nA munkám során mindig arra törekszem, hogy a tabló tökéletesen tükrözze az osztály hangulatát és egyéniségét.\n\nBármilyen kérdésetek van, nyugodtan keressetek!",
                'comments' => 6,
                'reactions' => ['❤️' => 18],
            ],
            [
                'title' => 'Tippek a fotózáshoz',
                'content' => "Néhány tipp a fotózáshoz:\n✅ Aludjatok eleget előtte\n✅ Öltözzetek semleges színekbe\n✅ Mosolyogjatok természetesen!",
                'comments' => 11,
                'reactions' => ['❤️' => 9, '🫡' => 4],
            ],
            [
                'title' => 'Háttérszín szavazás',
                'content' => 'Szavazzatok a háttérszínre a kommentekben!',
                'comments' => 25,
                'reactions' => ['👀' => 15],
            ],
            [
                'title' => 'Egyéni fotók rendelése',
                'content' => 'Aki szeretne extra egyéni fotókat rendelni, jelezze a kommentekben! Ár: 2.500 Ft/db',
                'comments' => 8,
                'reactions' => ['❤️' => 7],
            ],
            [
                'title' => 'Nyomda partnerünk',
                'content' => 'Profi nyomdával dolgozunk, akik már több száz tablót készítettek.',
                'comments' => 1,
                'reactions' => ['❤️' => 2],
            ],
            [
                'title' => 'Csomagolás és szállítás',
                'content' => 'A tablók gondosan csomagolva érkeznek majd. A szállítás díjmentes, az iskolába szállítunk.',
                'comments' => 3,
                'reactions' => ['❤️' => 5],
            ],
            [
                'title' => 'GYIK frissítve',
                'content' => 'A gyakran ismételt kérdések oldal frissítve lett.',
                'comments' => 0,
                'reactions' => ['🫡' => 3],
            ],
            [
                'title' => 'Köszönjük a türelmeteket!',
                'content' => "Kedves Diákok és Szülők!\n\nSzeretnénk megköszönni a türelmeteket és együttműködéseteket. A tablókészítés minden lépését igyekszünk a lehető legjobban megoldani.\n\nA végleges tablók hamarosan készek lesznek, és reméljük, hogy mindenkinek tetszeni fognak. Ez egy különleges emlék lesz a diákévekről!",
                'comments' => 30,
                'reactions' => ['❤️' => 40, '💀' => 5],
            ],
        ];

        $createdPosts = [];
        foreach ($posts as $i => $postData) {
            $post = TabloNewsfeedPost::create([
                'tablo_project_id' => $this->projectId,
                'author_type' => $i % 3 === 0 ? 'contact' : 'guest',
                'author_id' => $i % 3 === 0 ? 1 : $this->guestId,
                'post_type' => 'announcement',
                'title' => $postData['title'],
                'content' => $postData['content'],
                'is_pinned' => $i === 0, // First post is pinned
                'created_at' => now()->subDays(18 - $i)->subHours(rand(0, 12)),
            ]);

            $this->addReactions($post, $postData['reactions']);
            $this->addComments($post, $postData['comments']);

            $createdPosts[] = $post;
        }

        return $createdPosts;
    }

    private function createEvents(): array
    {
        $events = [
            [
                'title' => 'Közös fotózás',
                'content' => 'Mindenki legyen ott időben!',
                'event_date' => now()->addDays(7)->toDateString(),
                'event_time' => '09:00',
                'event_location' => 'Iskola aula',
                'comments' => 15,
                'reactions' => ['❤️' => 20],
            ],
            [
                'title' => 'Egyéni fotózás A-K',
                'content' => 'Vezetéknevek A-tól K-ig.',
                'event_date' => now()->addDays(10)->toDateString(),
                'event_time' => '10:00',
                'event_location' => 'Stúdió',
                'comments' => 8,
                'reactions' => ['❤️' => 12],
            ],
            [
                'title' => 'Egyéni fotózás L-Z',
                'content' => 'Vezetéknevek L-től Z-ig.',
                'event_date' => now()->addDays(12)->toDateString(),
                'event_time' => '10:00',
                'event_location' => 'Stúdió',
                'comments' => 6,
                'reactions' => ['❤️' => 10],
            ],
            [
                'title' => 'Sablon bemutató',
                'content' => 'Online bemutatjuk az elérhető sablonokat.',
                'event_date' => now()->addDays(5)->toDateString(),
                'event_time' => '18:00',
                'event_location' => 'Online (Zoom)',
                'comments' => 4,
                'reactions' => ['👀' => 8],
            ],
            [
                'title' => 'Szülői értekezlet',
                'content' => 'A tablókészítés részleteiről.',
                'event_date' => now()->addDays(14)->toDateString(),
                'event_time' => '17:00',
                'event_location' => 'Tanterem',
                'comments' => 22,
                'reactions' => ['😭' => 6, '❤️' => 8],
            ],
            [
                'title' => 'Fizetési határidő',
                'content' => 'Utolsó nap a befizetésre!',
                'event_date' => now()->addDays(21)->toDateString(),
                'event_time' => null,
                'event_location' => null,
                'comments' => 0,
                'reactions' => ['😭' => 15],
            ],
            [
                'title' => 'Próbanyomat átvétel',
                'content' => 'A próbanyomatok átvétele.',
                'event_date' => now()->addDays(30)->toDateString(),
                'event_time' => '14:00',
                'event_location' => 'Porta',
                'comments' => 3,
                'reactions' => ['❤️' => 5],
            ],
            [
                'title' => 'Javítási kérelmek',
                'content' => 'Utolsó lehetőség javításokat kérni.',
                'event_date' => now()->addDays(35)->toDateString(),
                'event_time' => '23:59',
                'event_location' => 'Online',
                'comments' => 9,
                'reactions' => ['👀' => 4],
            ],
            [
                'title' => 'Végleges nyomtatás',
                'content' => 'A tablók nyomtatásra kerülnek.',
                'event_date' => now()->addDays(45)->toDateString(),
                'event_time' => null,
                'event_location' => null,
                'comments' => 2,
                'reactions' => ['🫡' => 12],
            ],
            [
                'title' => 'Tablók átadása 🎓',
                'content' => 'Ünnepi tabló átadás a díszteremben!',
                'event_date' => now()->addDays(60)->toDateString(),
                'event_time' => '16:00',
                'event_location' => 'Díszterem',
                'comments' => 35,
                'reactions' => ['❤️' => 50, '💀' => 3],
            ],
            [
                'title' => 'Ballagási fotózás',
                'content' => 'Csoportos és egyéni fotók a ballagáson.',
                'event_date' => now()->addDays(90)->toDateString(),
                'event_time' => '10:00',
                'event_location' => 'Iskola',
                'comments' => 18,
                'reactions' => ['❤️' => 30],
            ],
            [
                'title' => 'Utórendelés határidő',
                'content' => 'Utolsó lehetőség extra tablót rendelni.',
                'event_date' => now()->addDays(120)->toDateString(),
                'event_time' => '23:59',
                'event_location' => 'Online',
                'comments' => 5,
                'reactions' => ['❤️' => 8],
            ],
        ];

        $createdEvents = [];
        foreach ($events as $i => $eventData) {
            $post = TabloNewsfeedPost::create([
                'tablo_project_id' => $this->projectId,
                'author_type' => 'contact',
                'author_id' => 1,
                'post_type' => 'event',
                'title' => $eventData['title'],
                'content' => $eventData['content'],
                'event_date' => $eventData['event_date'],
                'event_time' => $eventData['event_time'],
                'event_location' => $eventData['event_location'],
                'is_pinned' => false,
                'created_at' => now()->subDays(12 - $i)->subHours(rand(0, 8)),
            ]);

            $this->addReactions($post, $eventData['reactions']);
            $this->addComments($post, $eventData['comments']);

            $createdEvents[] = $post;
        }

        return $createdEvents;
    }

    private function addReactions(TabloNewsfeedPost $post, array $reactions): void
    {
        $guestIds = range(10, 50);

        foreach ($reactions as $emoji => $count) {
            for ($i = 0; $i < $count; $i++) {
                $guestId = $guestIds[array_rand($guestIds)];

                TabloNewsfeedLike::updateOrCreate(
                    [
                        'tablo_newsfeed_post_id' => $post->id,
                        'liker_type' => 'guest',
                        'liker_id' => $guestId,
                    ],
                    [
                        'reaction' => $emoji,
                        'created_at' => now()->subHours(rand(1, 48)),
                    ]
                );
            }
        }

        $post->updateLikesCount();
    }

    private function addComments(TabloNewsfeedPost $post, int $count): void
    {
        $commentTemplates = [
            'Kérdések' => [
                'Mikor lesz pontosan?',
                'Ez kötelező?',
                'Lehet módosítani utólag?',
                'Hogyan tudok fizetni?',
                'Kinek szóljak, ha kérdésem van?',
            ],
            'Pozitív' => [
                'Szuper!',
                'Nagyon szép!',
                'Alig várom!',
                'Király!',
                'Nagyon tetszik!',
                'Köszönjük!',
                'Ez lesz a legjobb tabló!',
            ],
            'Semleges' => [
                'Oké, értem.',
                'Rendben.',
                'Köszönöm az infót!',
                'Átadom a szüleimnek.',
            ],
        ];

        $guestIds = range(10, 40);
        $guestNames = [
            10 => 'Kovács Péter',
            11 => 'Nagy Anna',
            12 => 'Szabó Dávid',
            13 => 'Tóth Eszter',
            14 => 'Kiss Bence',
            15 => 'Horváth Lilla',
            16 => 'Varga Máté',
            17 => 'Molnár Zsófia',
            18 => 'Ákos',
            19 => 'Fekete Réka',
            20 => 'Balogh Gergő',
        ];

        $parentIds = [];

        for ($i = 0; $i < $count; $i++) {
            $guestId = $guestIds[array_rand($guestIds)];
            $category = array_rand($commentTemplates);
            $templates = $commentTemplates[$category];
            $content = $templates[array_rand($templates)];

            // 30% chance to be a reply
            $parentId = null;
            if ($i > 0 && count($parentIds) > 0 && rand(1, 100) <= 30) {
                $parentId = $parentIds[array_rand($parentIds)];
            }

            $comment = TabloNewsfeedComment::create([
                'tablo_newsfeed_post_id' => $post->id,
                'parent_id' => $parentId,
                'author_type' => 'guest',
                'author_id' => $guestId,
                'content' => $content,
                'created_at' => now()->subHours(rand(1, 72)),
            ]);

            // Root comments can be parents
            if (! $parentId) {
                $parentIds[] = $comment->id;
            }
        }

        $post->updateCommentsCount();
    }
}
