<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\WorkSession;
use Illuminate\Database\Seeder;

class TabloModeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a work session in tablo mode
        $workSession = WorkSession::create([
            'name' => 'Tablófotózás - 8.A Osztály 2025',
            'description' => 'Tavaszi osztályfotózás tablókép választással',
            'digit_code_enabled' => true,
            'digit_code' => '888888',
            'digit_code_expires_at' => now()->addDays(30),
            'status' => 'active',
            'is_tablo_mode' => true,
            'max_retouch_photos' => 5,
        ]);

        // Create parent album for shared photos
        $album = Album::create([
            'title' => 'Tavaszi Tablófotózás - 8.A',
            'name' => '8a-tavasz-tablo',
            'visibility' => 'link',
            'status' => 'active',
        ]);

        // Associate album with work session
        $workSession->albums()->attach($album->id);

        $this->command->info('✅ Tablo mode work session created:');
        $this->command->info("   Munkamenet: {$workSession->name}");
        $this->command->info("   Belépési kód: {$workSession->digit_code}");
        $this->command->info("   Album: {$album->title}");
        $this->command->info("   Max retusált képek: {$workSession->max_retouch_photos}");
        $this->command->newLine();
        $this->command->warn('📸 Ne felejtsd el feltölteni a képeket az albumba!');
        $this->command->info('   Használd a Filament admin-t: http://localhost:8000/admin');
    }
}
