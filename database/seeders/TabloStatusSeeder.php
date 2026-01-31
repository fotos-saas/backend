<?php

namespace Database\Seeders;

use App\Models\TabloStatus;
use Illuminate\Database\Seeder;

/**
 * TabloStatusSeeder - Seed default tablo statuses.
 */
class TabloStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Nincs elkezdve',
                'slug' => 'not-started',
                'color' => 'gray',
                'icon' => 'heroicon-o-clock',
                'sort_order' => 1,
            ],
            [
                'name' => 'Folyamatban',
                'slug' => 'in-progress',
                'color' => 'blue',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort_order' => 2,
            ],
            [
                'name' => 'Képekre várunk',
                'slug' => 'waiting-photos',
                'color' => 'amber',
                'icon' => 'heroicon-o-photo',
                'sort_order' => 3,
            ],
            [
                'name' => 'Kész',
                'slug' => 'completed',
                'color' => 'green',
                'icon' => 'heroicon-o-check-circle',
                'sort_order' => 4,
            ],
            [
                'name' => 'Innentől a fotós intézi',
                'slug' => 'photographer-handling',
                'color' => 'purple',
                'icon' => 'heroicon-o-user',
                'sort_order' => 5,
            ],
        ];

        foreach ($statuses as $status) {
            TabloStatus::updateOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
}
