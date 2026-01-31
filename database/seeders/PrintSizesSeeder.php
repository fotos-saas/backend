<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrintSizesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sizes = [
            ['name' => 'Igazolványkép', 'width_mm' => 35, 'height_mm' => 45, 'weight_grams' => 5],
            ['name' => '9x13 cm', 'width_mm' => 90, 'height_mm' => 130, 'weight_grams' => 20],
            ['name' => '10x15 cm', 'width_mm' => 100, 'height_mm' => 150, 'weight_grams' => 30],
            ['name' => '13x18 cm', 'width_mm' => 130, 'height_mm' => 180, 'weight_grams' => 50],
            ['name' => '15x21 cm', 'width_mm' => 150, 'height_mm' => 210, 'weight_grams' => 70],
            ['name' => '18x24 cm', 'width_mm' => 180, 'height_mm' => 240, 'weight_grams' => 100],
            ['name' => '20x30 cm', 'width_mm' => 200, 'height_mm' => 300, 'weight_grams' => 150],
        ];

        foreach ($sizes as $size) {
            // Check if exists
            $existing = DB::table('print_sizes')->where('name', $size['name'])->first();

            if ($existing) {
                // Update only if values changed
                DB::table('print_sizes')
                    ->where('name', $size['name'])
                    ->update([
                        'width_mm' => $size['width_mm'],
                        'height_mm' => $size['height_mm'],
                        'weight_grams' => $size['weight_grams'],
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert new
                DB::table('print_sizes')->insert(array_merge($size, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        // Create default price list with sample prices
        $priceListId = DB::table('price_lists')->insertGetId([
            'name' => 'Alapértelmezett árlista',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $printSizes = DB::table('print_sizes')->get();

        // Set default print size (9x13 cm)
        $default9x13 = $printSizes->firstWhere('name', '9x13 cm');
        if ($default9x13) {
            DB::table('price_lists')
                ->where('id', $priceListId)
                ->update(['default_print_size_id' => $default9x13->id]);
        }
        $basePrices = [
            'Igazolványkép' => 479,
            '9x13 cm' => 120,
            '10x15 cm' => 150,
            '13x18 cm' => 250,
            '15x21 cm' => 400,
            '18x24 cm' => 499,
            '20x30 cm' => 990,
        ];

        foreach ($printSizes as $size) {
            DB::table('prices')->insert([
                'price_list_id' => $priceListId,
                'print_size_id' => $size->id,
                'price' => $basePrices[$size->name] ?? 500,
                'volume_discounts' => json_encode([
                    ['minQty' => 50, 'percentOff' => 10],
                    ['minQty' => 100, 'percentOff' => 15],
                    ['minQty' => 200, 'percentOff' => 20],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
