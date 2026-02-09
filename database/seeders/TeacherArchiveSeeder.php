<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TabloSchool;
use App\Models\TeacherAlias;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use App\Models\TeacherPhoto;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Seeder: 200 tanár a Nové Nové partnerhez (ID: 23), placeholder avatárokkal,
 * changelog szimulációval, év-tartomány fotókkal.
 *
 * Futtatás: php artisan db:seed --class=TeacherArchiveSeeder
 * Visszavonás: php artisan db:seed --class=TeacherArchiveSeeder -- --down
 */
class TeacherArchiveSeeder extends Seeder
{
    private const PARTNER_ID = 23;
    private const TEACHER_COUNT = 200;

    /** Magyar keresztnevek */
    private const FIRST_NAMES_FEMALE = [
        'Ágnes', 'Anna', 'Anita', 'Andrea', 'Beáta', 'Boglárka', 'Csilla',
        'Dóra', 'Edina', 'Emese', 'Erika', 'Eszter', 'Éva', 'Fruzsina',
        'Gabriella', 'Hajnalka', 'Ildikó', 'Ilona', 'Irén', 'Judit',
        'Julianna', 'Katalin', 'Klára', 'Krisztina', 'Laura', 'Lídia',
        'Magdolna', 'Margit', 'Mária', 'Mariann', 'Márta', 'Melinda',
        'Mónika', 'Nóra', 'Orsolya', 'Piroska', 'Réka', 'Rita', 'Rozália',
        'Sarolta', 'Szilvia', 'Teodóra', 'Tímea', 'Veronika', 'Viktória',
        'Vivien', 'Zita', 'Zsófia', 'Zsuzsa', 'Zsuzsanna',
    ];

    private const FIRST_NAMES_MALE = [
        'Ádám', 'András', 'Attila', 'Balázs', 'Béla', 'Csaba', 'Dániel',
        'Dénes', 'Ferenc', 'Gábor', 'Gergely', 'György', 'Gyula', 'Imre',
        'István', 'János', 'József', 'Kálmán', 'Károly', 'László',
        'Levente', 'Máté', 'Miklós', 'Norbert', 'Pál', 'Péter', 'Richárd',
        'Róbert', 'Sándor', 'Tamás', 'Tibor', 'Viktor', 'Zoltán', 'Zsolt',
    ];

    private const LAST_NAMES = [
        'Nagy', 'Kovács', 'Tóth', 'Szabó', 'Horváth', 'Varga', 'Kiss',
        'Molnár', 'Németh', 'Farkas', 'Balogh', 'Papp', 'Takács', 'Juhász',
        'Lakatos', 'Mészáros', 'Oláh', 'Simon', 'Rácz', 'Fekete',
        'Szilágyi', 'Török', 'Fehér', 'Balázs', 'Gál', 'Kis', 'Szűcs',
        'Kocsis', 'Orsós', 'Pintér', 'Fodor', 'Szalai', 'Sipos', 'Magyar',
        'Lukács', 'Gulyás', 'Biró', 'Király', 'Katona', 'László',
        'Jakab', 'Bogdán', 'Balog', 'Sárközi', 'Hegedűs', 'Kelemen',
        'Bodnár', 'Halász', 'Hajdu', 'Máté',
    ];

    private const TITLE_PREFIXES = [null, null, null, null, null, 'Dr.', 'Dr.', 'PhD', 'Prof.', 'habil.'];

    /** Szín paletta az avatárokhoz (RGBA) */
    private const AVATAR_COLORS = [
        [99, 102, 241],   // indigo
        [139, 92, 246],   // violet
        [236, 72, 153],   // pink
        [244, 114, 22],   // orange
        [16, 185, 129],   // emerald
        [20, 184, 166],   // teal
        [59, 130, 246],   // blue
        [245, 158, 11],   // amber
        [239, 68, 68],    // red
        [34, 197, 94],    // green
        [168, 85, 247],   // purple
        [14, 165, 233],   // sky
    ];

    public function run(): void
    {
        if (in_array('--down', $_SERVER['argv'] ?? [])) {
            $this->down();
            return;
        }

        // Iskolák lekérdezése ehhez a partnerhez
        $schoolIds = DB::table('partner_schools')
            ->where('partner_id', self::PARTNER_ID)
            ->pluck('school_id')
            ->toArray();

        if (empty($schoolIds)) {
            // Fallback: az összes iskola, ami a CsongradSchoolsSeeder-ben van
            $schoolIds = TabloSchool::whereIn('name', [
                'Szegedi Radnóti Miklós Kísérleti Gimnázium',
                'Szegedi Tömörkény István Gimnázium',
                'Dugonics András Piarista Gimnázium',
                'SZTE Gyakorló Gimnázium és Általános Iskola',
                'Szegedi Deák Ferenc Gimnázium',
            ])->pluck('id')->toArray();
        }

        if (empty($schoolIds)) {
            $this->command->error('Nincsenek iskolák a partnerhez! Futtasd előbb: CsongradSchoolsSeeder');
            return;
        }

        $this->command->info("Iskolák: " . count($schoolIds) . " db");
        $this->command->info("Tanárok létrehozása: " . self::TEACHER_COUNT . " db...");

        $bar = $this->command->getOutput()->createProgressBar(self::TEACHER_COUNT);
        $usedNames = [];

        for ($i = 0; $i < self::TEACHER_COUNT; $i++) {
            // Egyedi név generálás
            do {
                $isFemale = rand(0, 1) === 0;
                $firstName = $isFemale
                    ? self::FIRST_NAMES_FEMALE[array_rand(self::FIRST_NAMES_FEMALE)]
                    : self::FIRST_NAMES_MALE[array_rand(self::FIRST_NAMES_MALE)];
                $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                $fullName = "$lastName $firstName";
            } while (in_array($fullName, $usedNames));
            $usedNames[] = $fullName;

            $titlePrefix = self::TITLE_PREFIXES[array_rand(self::TITLE_PREFIXES)];
            $schoolId = $schoolIds[array_rand($schoolIds)];

            // Tanár létrehozása
            $teacher = TeacherArchive::create([
                'partner_id' => self::PARTNER_ID,
                'school_id' => $schoolId,
                'canonical_name' => $fullName,
                'title_prefix' => $titlePrefix,
                'notes' => $this->generateNotes($i),
                'is_active' => rand(1, 20) !== 1, // 5% inaktív
            ]);

            // Aliasok (30%-nak van alias)
            if (rand(1, 100) <= 30) {
                $this->createAliases($teacher, $firstName, $lastName, $isFemale);
            }

            // Fotók év-tartományokkal
            $photoConfig = $this->getPhotoConfig($i);
            $activePhotoId = null;

            foreach ($photoConfig['years'] as $yearIndex => $year) {
                $mediaId = $this->createPlaceholderPhoto($teacher, $fullName, $year);

                if ($mediaId) {
                    $isActive = ($yearIndex === count($photoConfig['years']) - 1); // legfrissebb = aktív

                    $teacherPhoto = TeacherPhoto::create([
                        'teacher_id' => $teacher->id,
                        'media_id' => $mediaId,
                        'year' => $year,
                        'is_active' => $isActive,
                    ]);

                    if ($isActive) {
                        $activePhotoId = $mediaId;
                    }

                    // Photo upload changelog
                    TeacherChangeLog::create([
                        'teacher_id' => $teacher->id,
                        'change_type' => 'photo_uploaded',
                        'new_value' => "{$fullName}_{$year}.png",
                        'metadata' => json_encode(['year' => $year, 'media_id' => $mediaId]),
                        'created_at' => Carbon::create($year, rand(8, 11), rand(1, 28)),
                    ]);
                }
            }

            if ($activePhotoId) {
                $teacher->update(['active_photo_id' => $activePhotoId]);
            }

            // Created changelog
            TeacherChangeLog::create([
                'teacher_id' => $teacher->id,
                'change_type' => 'created',
                'new_value' => $teacher->full_display_name,
                'created_at' => Carbon::now()->subDays(rand(30, 365)),
            ]);

            // Szimulált changelog (névváltozás, iskolaváltás)
            $this->simulateChangelogs($teacher, $schoolIds);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        $total = TeacherArchive::where('partner_id', self::PARTNER_ID)->count();
        $withPhotos = TeacherArchive::where('partner_id', self::PARTNER_ID)->whereNotNull('active_photo_id')->count();
        $this->command->info("Kész! Összesen: {$total} tanár, ebből fotóval: {$withPhotos}");
    }

    /**
     * Foto konfiguráció: egyeseknek nincs kép, másoknak 1-5 különböző évből
     */
    private function getPhotoConfig(int $index): array
    {
        $rand = rand(1, 100);

        // 15% - nincs fotó
        if ($rand <= 15) {
            return ['years' => []];
        }

        // 35% - 1 fotó (csak 2026)
        if ($rand <= 50) {
            return ['years' => [2026]];
        }

        // 25% - 2 fotó (pl. 2024 + 2026)
        if ($rand <= 75) {
            $startYear = rand(2020, 2025);
            return ['years' => [$startYear, 2026]];
        }

        // 15% - 3-4 fotó (több évből)
        if ($rand <= 90) {
            $years = [];
            $start = rand(2010, 2020);
            $count = rand(3, 4);
            for ($j = 0; $j < $count; $j++) {
                $years[] = min($start + $j * rand(2, 4), 2026);
            }
            return ['years' => array_unique($years)];
        }

        // 10% - 5+ fotó (régóta aktív tanár, pl. 2005-2026)
        $years = [];
        $start = rand(2005, 2010);
        $count = rand(5, 7);
        for ($j = 0; $j < $count; $j++) {
            $years[] = min($start + $j * rand(2, 4), 2026);
        }
        return ['years' => array_unique($years)];
    }

    /**
     * Placeholder portré kép generálása Imagick-kel és Spatie MediaLibrary-vel feltöltés
     */
    private function createPlaceholderPhoto(TeacherArchive $teacher, string $name, int $year): ?int
    {
        if (!extension_loaded('imagick')) {
            $this->command->warn('Imagick extension nincs telepítve, fotók kihagyva.');
            return null;
        }

        $size = 400;

        // Háttérszín a név hash alapján (determinisztikus)
        $colorIndex = crc32($name . $year) % count(self::AVATAR_COLORS);
        if ($colorIndex < 0) {
            $colorIndex += count(self::AVATAR_COLORS);
        }
        [$r, $g, $b] = self::AVATAR_COLORS[$colorIndex];

        $bgHex = sprintf('#%02x%02x%02x', $r, $g, $b);
        $lightHex = sprintf('#%02x%02x%02x', min($r + 40, 255), min($g + 40, 255), min($b + 40, 255));
        $yearHex = sprintf('#%02x%02x%02x', min($r + 60, 255), min($g + 60, 255), min($b + 60, 255));

        $img = new \Imagick();
        $img->newImage($size, $size, new \ImagickPixel($bgHex));
        $img->setImageFormat('png');

        // Fejkontúr (világosabb kör)
        $headDraw = new \ImagickDraw();
        $headDraw->setFillColor(new \ImagickPixel($lightHex));
        $headY = (int)($size * 0.35);
        $headRadius = (int)($size * 0.22);
        $headDraw->circle(
            (int)($size / 2), $headY,
            (int)($size / 2) + $headRadius, $headY
        );

        // Váll/test (alsó ellipszis)
        $bodyY = (int)($size * 0.78);
        $bodyRx = (int)($size * 0.325);
        $bodyRy = (int)($size * 0.225);
        $headDraw->ellipse(
            (int)($size / 2), $bodyY,
            $bodyRx, $bodyRy,
            0, 360
        );
        $img->drawImage($headDraw);
        $headDraw->destroy();

        // Initials (betűk)
        $parts = explode(' ', $name);
        $initials = count($parts) >= 2
            ? mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1)
            : mb_substr($name, 0, 2);
        $initials = mb_strtoupper($initials);

        $fontPath = storage_path('fonts/NotoSans-Bold.ttf');

        $textDraw = new \ImagickDraw();
        $textDraw->setFont($fontPath);
        $textDraw->setFillColor(new \ImagickPixel('#ffffff'));
        $textDraw->setFontSize(48);
        $textDraw->setGravity(\Imagick::GRAVITY_CENTER);
        $textDraw->setTextAlignment(\Imagick::ALIGN_CENTER);

        // Initials a fej közepére
        $img->annotateImage($textDraw, 0, -((int)($size * 0.15)), 0, $initials);
        $textDraw->destroy();

        // Év a jobb alsó sarokba
        $yearDraw = new \ImagickDraw();
        $yearDraw->setFont($fontPath);
        $yearDraw->setFillColor(new \ImagickPixel($yearHex));
        $yearDraw->setFontSize(18);
        $yearDraw->setGravity(\Imagick::GRAVITY_SOUTHEAST);
        $img->annotateImage($yearDraw, 10, 8, 0, (string)$year);
        $yearDraw->destroy();

        // Temp fájl mentése
        $tmpPath = tempnam(sys_get_temp_dir(), 'teacher_avatar_');
        $tmpFile = $tmpPath . '.png';
        rename($tmpPath, $tmpFile);
        $img->writeImage($tmpFile);
        $img->clear();
        $img->destroy();

        try {
            $media = $teacher->addMedia($tmpFile)
                ->usingFileName("{$teacher->id}_{$year}.png")
                ->toMediaCollection('teacher_photos');

            return $media->id;
        } catch (\Throwable $e) {
            @unlink($tmpFile);
            return null;
        }
    }

    /**
     * Aliasok létrehozása
     */
    private function createAliases(TeacherArchive $teacher, string $firstName, string $lastName, bool $isFemale): void
    {
        $aliases = [];

        // Fordított névsorrend (magyar → angol)
        $aliases[] = "$firstName $lastName";

        // Becenév
        if ($isFemale && rand(0, 1)) {
            $suffix = rand(0, 1) ? ' néni' : ' tanárnő';
            $aliases[] = $firstName . $suffix;
        }
        if (!$isFemale && rand(0, 1)) {
            $aliases[] = $lastName . ' tanár úr';
        }

        // Rövidített
        if (rand(0, 1)) {
            $aliases[] = mb_substr($lastName, 0, 1) . '. ' . $firstName;
        }

        foreach (array_slice($aliases, 0, 3) as $alias) {
            TeacherAlias::create([
                'teacher_id' => $teacher->id,
                'alias_name' => $alias,
            ]);
        }
    }

    /**
     * Changelog szimulálás (névváltozás, iskolaváltás, stb.)
     */
    private function simulateChangelogs(TeacherArchive $teacher, array $schoolIds): void
    {
        $rand = rand(1, 100);

        // 20% - névváltozás
        if ($rand <= 20) {
            $oldName = self::LAST_NAMES[array_rand(self::LAST_NAMES)] . 'né ' .
                mb_substr($teacher->canonical_name, strpos($teacher->canonical_name, ' ') + 1);

            TeacherChangeLog::create([
                'teacher_id' => $teacher->id,
                'change_type' => 'name_changed',
                'old_value' => $oldName,
                'new_value' => $teacher->canonical_name,
                'created_at' => Carbon::now()->subDays(rand(10, 200)),
            ]);
        }

        // 15% - iskolaváltás
        if ($rand <= 15 && count($schoolIds) > 1) {
            $otherSchools = array_diff($schoolIds, [$teacher->school_id]);
            if (!empty($otherSchools)) {
                $oldSchoolId = $otherSchools[array_rand($otherSchools)];
                TeacherChangeLog::create([
                    'teacher_id' => $teacher->id,
                    'change_type' => 'school_changed',
                    'old_value' => (string)$oldSchoolId,
                    'new_value' => (string)$teacher->school_id,
                    'created_at' => Carbon::now()->subDays(rand(10, 150)),
                ]);
            }
        }

        // 10% - title változás
        if ($rand <= 10 && $teacher->title_prefix) {
            TeacherChangeLog::create([
                'teacher_id' => $teacher->id,
                'change_type' => 'title_changed',
                'old_value' => null,
                'new_value' => $teacher->title_prefix,
                'created_at' => Carbon::now()->subDays(rand(5, 100)),
            ]);
        }

        // 8% - aktív fotó változás
        if ($rand <= 8) {
            TeacherChangeLog::create([
                'teacher_id' => $teacher->id,
                'change_type' => 'active_photo_changed',
                'new_value' => 'avatar_updated.png',
                'metadata' => json_encode(['year' => rand(2020, 2026)]),
                'created_at' => Carbon::now()->subDays(rand(1, 60)),
            ]);
        }
    }

    /**
     * Megjegyzések generálása (néhány tanárnak)
     */
    private function generateNotes(int $index): ?string
    {
        $notes = [
            null, null, null, null, null, null, null, // 70% null
            'Osztályfőnök',
            'Nyugdíjas, helyettesít',
            'Félállásban tanít',
            'GYES-en van, 2027-ben tér vissza',
            'Testnevelés + biológia szak',
            'Tagozatos matematika tanár',
            'Nyelvi labor felelős',
            'Diákönkormányzat patronáló tanár',
            'Természettudományos tagozat vezető',
            'Informatika szaktanterem felelős',
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Visszavonás: ezen seeder által létrehozott tanárok törlése
     */
    public function down(): void
    {
        $this->command->info('Tanárok törlése a partner #' . self::PARTNER_ID . '-hez...');

        $teachers = TeacherArchive::where('partner_id', self::PARTNER_ID)->get();
        $count = $teachers->count();

        if ($count === 0) {
            $this->command->warn('Nincsenek tanárok ehhez a partnerhez.');
            return;
        }

        $bar = $this->command->getOutput()->createProgressBar($count);

        foreach ($teachers as $teacher) {
            // Spatie media törlés (fájlok is)
            $teacher->clearMediaCollection('teacher_photos');

            // Changelogs, photos, aliases cascade delete-tel mennek
            $teacher->delete();

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Törölve: {$count} tanár az összes kapcsolódó adattal.");
    }
}
