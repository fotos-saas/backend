<?php

namespace Database\Seeders;

use App\Models\PartnerSetting;
use Illuminate\Database\Seeder;

class PartnerSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (PartnerSetting::query()->exists()) {
            return;
        }

        PartnerSetting::create([
            'name' => config('app.name', 'Photo Stack'),
            'is_active' => true,
        ]);
    }
}
