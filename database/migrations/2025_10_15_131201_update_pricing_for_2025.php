<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Update 2025 pricing for print sizes and shipping methods
     */
    public function up(): void
    {
        // Update print size prices (based on highest online prices for glossy paper)
        $printPrices = [
            'Igazolványkép' => 479,
            '9x13 cm' => 120,
            '10x15 cm' => 150,
            '13x18 cm' => 250,
            '15x21 cm' => 400,
            '18x24 cm' => 499,
            '20x30 cm' => 990,
        ];

        foreach ($printPrices as $sizeName => $price) {
            DB::table('prices')
                ->join('print_sizes', 'prices.print_size_id', '=', 'print_sizes.id')
                ->where('print_sizes.name', $sizeName)
                ->update(['prices.price' => $price]);
        }

        // Update MPL courier rates (2025 house delivery)
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 0)
            ->where('shipping_rates.weight_to_grams', 2000)
            ->update(['shipping_rates.price_huf' => 2605]);

        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 2001)
            ->where('shipping_rates.weight_to_grams', 5000)
            ->update(['shipping_rates.price_huf' => 2950]);

        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 5001)
            ->whereNull('shipping_rates.weight_to_grams')
            ->update(['shipping_rates.price_huf' => 3130]);

        // Update Foxpost parcel locker rate (registered user price)
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'foxpost')
            ->where('shipping_methods.type', 'parcel_locker')
            ->where('shipping_rates.weight_from_grams', 0)
            ->update(['shipping_rates.price_huf' => 1699]);

        // Update Magyar Posta normal letter rate
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'magyar_posta')
            ->where('shipping_methods.type', 'letter')
            ->where('shipping_rates.is_express', false)
            ->where('shipping_rates.weight_from_grams', 0)
            ->update(['shipping_rates.price_huf' => 270]);

        // Update Magyar Posta express letter rates (split into 2 tiers)
        // First, update existing 0-500g rate to 0-50g @ 390 Ft
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'magyar_posta')
            ->where('shipping_methods.type', 'letter')
            ->where('shipping_rates.is_express', true)
            ->where('shipping_rates.weight_from_grams', 0)
            ->update([
                'shipping_rates.weight_to_grams' => 50,
                'shipping_rates.price_huf' => 390,
            ]);

        // Insert new 51-500g rate @ 1115 Ft (if not exists)
        $expressMethod = DB::table('shipping_methods')
            ->where('provider', 'magyar_posta')
            ->where('type', 'letter')
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($expressMethod) {
            $existing51Rate = DB::table('shipping_rates')
                ->where('shipping_method_id', $expressMethod->id)
                ->where('weight_from_grams', 51)
                ->where('is_express', true)
                ->exists();

            if (! $existing51Rate) {
                DB::table('shipping_rates')->insert([
                    'shipping_method_id' => $expressMethod->id,
                    'weight_from_grams' => 51,
                    'weight_to_grams' => 500,
                    'price_huf' => 1115,
                    'is_express' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations - Restore old 2024 pricing
     */
    public function down(): void
    {
        // Restore old print size prices
        $oldPrintPrices = [
            'Igazolványkép' => 150,
            '9x13 cm' => 200,
            '10x15 cm' => 300,
            '13x18 cm' => 500,
            '15x21 cm' => 800,
            '18x24 cm' => 1200,
            '20x30 cm' => 1800,
        ];

        foreach ($oldPrintPrices as $sizeName => $price) {
            DB::table('prices')
                ->join('print_sizes', 'prices.print_size_id', '=', 'print_sizes.id')
                ->where('print_sizes.name', $sizeName)
                ->update(['prices.price' => $price]);
        }

        // Restore old MPL rates
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 0)
            ->where('shipping_rates.weight_to_grams', 2000)
            ->update(['shipping_rates.price_huf' => 1400]);

        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 2001)
            ->where('shipping_rates.weight_to_grams', 5000)
            ->update(['shipping_rates.price_huf' => 1900]);

        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'mpl')
            ->where('shipping_methods.type', 'courier')
            ->where('shipping_rates.weight_from_grams', 5001)
            ->whereNull('shipping_rates.weight_to_grams')
            ->update(['shipping_rates.price_huf' => 2400]);

        // Restore Foxpost rate
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'foxpost')
            ->where('shipping_methods.type', 'parcel_locker')
            ->where('shipping_rates.weight_from_grams', 0)
            ->update(['shipping_rates.price_huf' => 990]);

        // Restore Magyar Posta normal letter
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'magyar_posta')
            ->where('shipping_methods.type', 'letter')
            ->where('shipping_rates.is_express', false)
            ->where('shipping_rates.weight_from_grams', 0)
            ->update(['shipping_rates.price_huf' => 140]);

        // Restore Magyar Posta express letter (merge back to single 0-500g @ 200 Ft)
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'magyar_posta')
            ->where('shipping_methods.type', 'letter')
            ->where('shipping_rates.is_express', true)
            ->where('shipping_rates.weight_from_grams', 0)
            ->update([
                'shipping_rates.weight_to_grams' => 500,
                'shipping_rates.price_huf' => 200,
            ]);

        // Delete the 51-500g express rate
        DB::table('shipping_rates')
            ->join('shipping_methods', 'shipping_rates.shipping_method_id', '=', 'shipping_methods.id')
            ->where('shipping_methods.provider', 'magyar_posta')
            ->where('shipping_methods.type', 'letter')
            ->where('shipping_rates.is_express', true)
            ->where('shipping_rates.weight_from_grams', 51)
            ->delete();
    }
};
