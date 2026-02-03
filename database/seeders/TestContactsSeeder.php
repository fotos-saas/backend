<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Illuminate\Database\Seeder;

/**
 * Test Contacts Seeder
 *
 * Létrehoz 49 random kapcsolattartót egy partner projektjeihez.
 * Használat: php artisan db:seed --class=TestContactsSeeder
 *
 * Visszavonás: php artisan tinker
 * >>> TabloContact::where('email', 'like', '%@test-contact.local')->delete()
 */
class TestContactsSeeder extends Seeder
{
    /**
     * Magyar keresztnevek
     */
    private array $firstNames = [
        'Anna', 'Béla', 'Csaba', 'Dóra', 'Éva', 'Ferenc', 'Gábor', 'Hajnal',
        'István', 'Júlia', 'Katalin', 'László', 'Mária', 'Nóra', 'Orsolya',
        'Péter', 'Rita', 'Sándor', 'Tamás', 'Zoltán', 'Ágnes', 'Balázs',
        'Cecília', 'Dániel', 'Emese', 'Flóra', 'György', 'Henrietta', 'Imre',
        'János', 'Krisztina', 'Levente', 'Mónika', 'Nikolett', 'Olivér',
        'Patrik', 'Rebeka', 'Szabolcs', 'Tímea', 'Viktor', 'Zsófia', 'Ádám',
        'Brigitta', 'Csilla', 'Dénes', 'Eszter', 'Fruzsina', 'Gergő',
    ];

    /**
     * Magyar vezetéknevek
     */
    private array $lastNames = [
        'Nagy', 'Kovács', 'Tóth', 'Szabó', 'Horváth', 'Varga', 'Kiss', 'Molnár',
        'Németh', 'Farkas', 'Balogh', 'Papp', 'Takács', 'Juhász', 'Lakatos',
        'Mészáros', 'Oláh', 'Simon', 'Rácz', 'Fehér', 'Szilágyi', 'Török',
        'Vincze', 'Balázs', 'Gál', 'Fodor', 'Pintér', 'Antal', 'Székely',
        'Bíró', 'Szűcs', 'Kocsis', 'Hajdu', 'Halász', 'Kozma', 'Vámos',
    ];

    /**
     * Megjegyzés sablonok
     */
    private array $notes = [
        'Hívjuk vissza délután',
        'Csak emailen elérhető',
        'Reggel 9 előtt ne hívjuk',
        'Szülői munkaközösség vezetője',
        'Osztályfőnök',
        'Igazgató helyettes',
        'Fotózás koordinátor',
        'Gyorsan válaszol emailre',
        'SMS-t preferál',
        'Csak hétvégén elérhető',
        null,
        null,
        null,
    ];

    public function run(): void
    {
        // Partner ID - módosítsd ha másik partnerre kell
        $partnerId = 1;

        $partner = Partner::find($partnerId);

        if (! $partner) {
            $this->command->error("Partner #{$partnerId} nem található!");

            return;
        }

        // Projekt lekérése vagy létrehozása
        $project = TabloProject::where('partner_id', $partnerId)->first();

        if (! $project) {
            $this->command->warn("Partner #{$partnerId}-nek nincs projektje, létrehozunk egyet...");
            $project = TabloProject::create([
                'partner_id' => $partnerId,
                'name' => 'Test Projekt - Kapcsolattartók',
                'class_name' => 'Test osztály',
                'class_year' => date('Y'),
            ]);
        }

        $this->command->info("Kapcsolattartók létrehozása: Partner #{$partnerId}, Projekt #{$project->id}");

        $created = 0;

        for ($i = 0; $i < 49; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $name = "{$lastName} {$firstName}";

            // Egyedi email generálás
            $emailSlug = strtolower(
                $this->removeAccents($firstName).'.'.
                $this->removeAccents($lastName).'.'.
                ($i + 1)
            );

            TabloContact::create([
                'tablo_project_id' => $project->id,
                'name' => $name,
                'email' => "{$emailSlug}@test-contact.local",
                'phone' => $this->generateHungarianPhone(),
                'note' => $this->notes[array_rand($this->notes)],
                'is_primary' => $i === 0, // Első legyen primary
                'call_count' => rand(0, 5),
                'sms_count' => rand(0, 3),
                'last_contacted_at' => rand(0, 1) ? now()->subDays(rand(1, 30)) : null,
            ]);

            $created++;
        }

        $this->command->info("✓ {$created} kapcsolattartó létrehozva!");
        $this->command->newLine();
        $this->command->warn('Törléshez futtasd:');
        $this->command->line("php artisan tinker --execute=\"App\\Models\\TabloContact::where('email', 'like', '%@test-contact.local')->delete()\"");
    }

    /**
     * Magyar telefonszám generálása
     */
    private function generateHungarianPhone(): string
    {
        $prefixes = ['20', '30', '70', '31'];
        $prefix = $prefixes[array_rand($prefixes)];

        return '+36 '.$prefix.' '.rand(100, 999).' '.rand(1000, 9999);
    }

    /**
     * Ékezetek eltávolítása
     */
    private function removeAccents(string $string): string
    {
        $from = ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű', 'Á', 'É', 'Í', 'Ó', 'Ö', 'Ő', 'Ú', 'Ü', 'Ű'];
        $to = ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u', 'A', 'E', 'I', 'O', 'O', 'O', 'U', 'U', 'U'];

        return str_replace($from, $to, $string);
    }
}
