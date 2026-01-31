<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageItem;
use App\Models\PrintSize;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $printSizes = PrintSize::all()->keyBy('name');

        // Alapcsomag: 10x15 és 13x18 papírok elérhetők limittel
        $alapcsomag = Package::create([
            'name' => 'Alapcsomag',
            'album_id' => null, // Global
        ]);

        PackageItem::create([
            'package_id' => $alapcsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'min_qty' => 10,
            'max_qty' => 50,
            'discount_percent' => 5.00,
        ]);

        PackageItem::create([
            'package_id' => $alapcsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'min_qty' => 5,
            'max_qty' => 30,
        ]);

        // Prémium csomag: több méret, nagyobb kedvezmény
        $premiumCsomag = Package::create([
            'name' => 'Prémium csomag',
            'album_id' => null, // Global
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'min_qty' => 20,
            'max_qty' => 100,
            'discount_percent' => 10.00,
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'min_qty' => 10,
            'max_qty' => 50,
            'discount_percent' => 10.00,
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['15x21 cm']->id,
            'min_qty' => 5,
            'max_qty' => 30,
            'custom_price' => 600,
        ]);

        // Teljes csomag: összes méret elérhető, egyedi árazással
        $teljesCsomag = Package::create([
            'name' => 'Teljes csomag',
            'album_id' => null, // Global
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'min_qty' => 50,
            'discount_percent' => 15.00,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'min_qty' => 20,
            'discount_percent' => 15.00,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['15x21 cm']->id,
            'min_qty' => 10,
            'custom_price' => 500,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['18x24 cm']->id,
            'min_qty' => 5,
            'custom_price' => 900,
        ]);

        $this->command->info('✓ Created '.Package::count().' packages');
        $this->command->info('✓ Created '.PackageItem::count().' package items');
    }
}
