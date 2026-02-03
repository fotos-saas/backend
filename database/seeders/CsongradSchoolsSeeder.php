<?php

namespace Database\Seeders;

use App\Models\TabloSchool;
use App\Models\TabloProject;
use Illuminate\Database\Seeder;

/**
 * Seeder: 19 iskola Csongrád megyéből a Nové Nové partnerhez.
 *
 * Futtatás: php artisan db:seed --class=CsongradSchoolsSeeder
 * Visszavonás: php artisan db:seed --class=CsongradSchoolsSeeder -- --down
 */
class CsongradSchoolsSeeder extends Seeder
{
    private const PARTNER_ID = 23; // Nové Nové partner (tablo_partners.id)

    private const SCHOOLS = [
        ['name' => 'Szegedi Radnóti Miklós Kísérleti Gimnázium', 'city' => 'Szeged'],
        ['name' => 'Szegedi Tömörkény István Gimnázium', 'city' => 'Szeged'],
        ['name' => 'Dugonics András Piarista Gimnázium', 'city' => 'Szeged'],
        ['name' => 'SZTE Gyakorló Gimnázium és Általános Iskola', 'city' => 'Szeged'],
        ['name' => 'Szegedi Deák Ferenc Gimnázium', 'city' => 'Szeged'],
        ['name' => 'Vedres István Építőipari Technikum', 'city' => 'Szeged'],
        ['name' => 'Szegedi Móra Ferenc Általános Iskola', 'city' => 'Szeged'],
        ['name' => 'Hódmezővásárhelyi Bethlen Gábor Gimnázium', 'city' => 'Hódmezővásárhely'],
        ['name' => 'Hódmezővásárhelyi Németh László Gimnázium', 'city' => 'Hódmezővásárhely'],
        ['name' => 'Makói József Attila Gimnázium', 'city' => 'Makó'],
        ['name' => 'Makói Szent István Általános Iskola', 'city' => 'Makó'],
        ['name' => 'Szentesi Horváth Mihály Gimnázium', 'city' => 'Szentes'],
        ['name' => 'Szentesi Koszta József Általános Iskola', 'city' => 'Szentes'],
        ['name' => 'Csongrádi Batsányi János Gimnázium', 'city' => 'Csongrád'],
        ['name' => 'Csongrádi Széchenyi István Általános Iskola', 'city' => 'Csongrád'],
        ['name' => 'Mórahalmi Móra Ferenc Általános Iskola', 'city' => 'Mórahalom'],
        ['name' => 'Kisteleki Általános Iskola', 'city' => 'Kistelek'],
        ['name' => 'Mindszenti Általános Iskola', 'city' => 'Mindszent'],
        ['name' => 'Algyői Általános Iskola', 'city' => 'Algyő'],
    ];

    public function run(): void
    {
        // Check if --down flag is passed
        $isDown = in_array('--down', $_SERVER['argv'] ?? []);

        if ($isDown) {
            $this->down();
            return;
        }

        $this->command->info('Creating 19 schools from Csongrád county for partner #' . self::PARTNER_ID);

        foreach (self::SCHOOLS as $schoolData) {
            // Create or find school
            $school = TabloSchool::firstOrCreate(
                ['name' => $schoolData['name']],
                ['city' => $schoolData['city']]
            );

            // Create a project for this school linked to the partner
            $existingProject = TabloProject::where('partner_id', self::PARTNER_ID)
                ->where('school_id', $school->id)
                ->first();

            if (!$existingProject) {
                $classNames = ['12.A', '12.B', '12.C', '11.A', '11.B', '8.A', '8.B', '4.A', '4.B'];
                $className = $classNames[array_rand($classNames)];

                TabloProject::create([
                    'partner_id' => self::PARTNER_ID,
                    'school_id' => $school->id,
                    'class_name' => $className,
                    'class_year' => '2025/2026',
                    'status' => 'not_started',
                ]);

                $this->command->info("  Created: {$school->name} ({$school->city}) - {$className}");
            } else {
                $this->command->warn("  Skipped (exists): {$school->name}");
            }
        }

        $this->command->info('Done! Created schools for partner.');
    }

    public function down(): void
    {
        $this->command->info('Removing schools created by this seeder for partner #' . self::PARTNER_ID);

        foreach (self::SCHOOLS as $schoolData) {
            $school = TabloSchool::where('name', $schoolData['name'])->first();

            if ($school) {
                // Delete projects for this partner
                $deleted = TabloProject::where('partner_id', self::PARTNER_ID)
                    ->where('school_id', $school->id)
                    ->delete();

                if ($deleted) {
                    $this->command->info("  Deleted project for: {$school->name}");
                }

                // If no other projects use this school, delete the school too
                $otherProjects = TabloProject::where('school_id', $school->id)->count();
                if ($otherProjects === 0) {
                    $school->delete();
                    $this->command->info("  Deleted school: {$school->name}");
                }
            }
        }

        $this->command->info('Done! Cleanup complete.');
    }
}
