<?php

namespace Database\Seeders;

use App\Models\PackagePoint;
use Illuminate\Database\Seeder;

class PackagePointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packagePoints = [
            // Foxpost points (example data - real data will be synced via API)
            [
                'provider' => 'foxpost',
                'external_id' => 'FP001',
                'name' => 'Foxpost Automata - Oktogon',
                'address' => 'Teréz krt. 1.',
                'city' => 'Budapest',
                'zip' => '1066',
                'latitude' => 47.5059,
                'longitude' => 19.0629,
                'is_active' => true,
                'opening_hours' => json_encode(['0-24']),
                'last_synced_at' => now(),
            ],
            [
                'provider' => 'foxpost',
                'external_id' => 'FP002',
                'name' => 'Foxpost Automata - Debrecen Fórum',
                'address' => 'Csapó utca 30.',
                'city' => 'Debrecen',
                'zip' => '4031',
                'latitude' => 47.5316,
                'longitude' => 21.6273,
                'is_active' => true,
                'opening_hours' => json_encode(['0-24']),
                'last_synced_at' => now(),
            ],
            [
                'provider' => 'foxpost',
                'external_id' => 'FP003',
                'name' => 'Foxpost Automata - Szeged Árkád',
                'address' => 'Londoni krt. 3.',
                'city' => 'Szeged',
                'zip' => '6724',
                'latitude' => 46.2477,
                'longitude' => 20.1431,
                'is_active' => true,
                'opening_hours' => json_encode(['0-24']),
                'last_synced_at' => now(),
            ],

            // Packeta points (example data - real data will be synced via API)
            [
                'provider' => 'packeta',
                'external_id' => 'P001',
                'name' => 'Packeta pont - Budapest Corvin',
                'address' => 'Futó utca 37-45.',
                'city' => 'Budapest',
                'zip' => '1082',
                'latitude' => 47.4814,
                'longitude' => 19.0748,
                'is_active' => true,
                'opening_hours' => json_encode(['H-P: 8-20', 'Szo: 9-18', 'V: zárva']),
                'last_synced_at' => now(),
            ],
            [
                'provider' => 'packeta',
                'external_id' => 'P002',
                'name' => 'Packeta pont - Debrecen Piac utca',
                'address' => 'Piac utca 45.',
                'city' => 'Debrecen',
                'zip' => '4024',
                'latitude' => 47.5316,
                'longitude' => 21.6389,
                'is_active' => true,
                'opening_hours' => json_encode(['H-P: 8-18', 'Szo: 9-13', 'V: zárva']),
                'last_synced_at' => now(),
            ],
            [
                'provider' => 'packeta',
                'external_id' => 'P003',
                'name' => 'Packeta pont - Szeged Kálvária',
                'address' => 'Kálvária tér 7.',
                'city' => 'Szeged',
                'zip' => '6725',
                'latitude' => 46.2530,
                'longitude' => 20.1414,
                'is_active' => true,
                'opening_hours' => json_encode(['H-P: 8-19', 'Szo: 9-14', 'V: zárva']),
                'last_synced_at' => now(),
            ],
        ];

        foreach ($packagePoints as $point) {
            PackagePoint::updateOrCreate(
                [
                    'provider' => $point['provider'],
                    'external_id' => $point['external_id'],
                ],
                $point
            );
        }

        $this->command->info('Package points seeded successfully');
        $this->command->warn('Note: Run "php artisan package-points:sync" to sync real data from APIs');
    }
}
