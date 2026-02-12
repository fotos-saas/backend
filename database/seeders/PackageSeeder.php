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

        // Alapcsomag
        $alapcsomag = Package::create([
            'name' => 'Alapcsomag',
            'album_id' => null,
            'price' => 3000,
            'selectable_photos_count' => 1,
        ]);

        PackageItem::create([
            'package_id' => $alapcsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'quantity' => 10,
        ]);

        PackageItem::create([
            'package_id' => $alapcsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'quantity' => 5,
        ]);

        // Prémium csomag
        $premiumCsomag = Package::create([
            'name' => 'Prémium csomag',
            'album_id' => null,
            'price' => 6000,
            'selectable_photos_count' => 3,
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'quantity' => 20,
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'quantity' => 10,
        ]);

        PackageItem::create([
            'package_id' => $premiumCsomag->id,
            'print_size_id' => $printSizes['15x21 cm']->id,
            'quantity' => 5,
        ]);

        // Teljes csomag
        $teljesCsomag = Package::create([
            'name' => 'Teljes csomag',
            'album_id' => null,
            'price' => 12000,
            'selectable_photos_count' => 5,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['10x15 cm']->id,
            'quantity' => 50,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['13x18 cm']->id,
            'quantity' => 20,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['15x21 cm']->id,
            'quantity' => 10,
        ]);

        PackageItem::create([
            'package_id' => $teljesCsomag->id,
            'print_size_id' => $printSizes['18x24 cm']->id,
            'quantity' => 5,
        ]);

        $this->command->info('Created '.Package::count().' packages');
        $this->command->info('Created '.PackageItem::count().' package items');
    }
}
