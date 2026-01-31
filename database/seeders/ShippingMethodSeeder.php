<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset all is_default flags to false before setting new default
        ShippingMethod::query()->update(['is_default' => false]);

        // MPL Futár
        $mpl = ShippingMethod::updateOrCreate(
            ['provider' => 'mpl', 'type' => 'courier'],
            [
                'name' => 'MPL futár',
                'type' => 'courier',
                'provider' => 'mpl',
                'is_active' => true,
                'requires_address' => true,
                'requires_parcel_point' => false,
                'supports_cod' => true,
                'cod_fee_huf' => 400,
                'min_weight_grams' => null,
                'max_weight_grams' => 30000,
                'sort_order' => 1,
                'description' => 'MPL futárszolgálat - 1-3 munkanap',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $mpl->id, 'weight_from_grams' => 0],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => 2000,
                'price_huf' => 2605,
                'is_express' => false,
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $mpl->id, 'weight_from_grams' => 2001],
            [
                'weight_from_grams' => 2001,
                'weight_to_grams' => 5000,
                'price_huf' => 2950,
                'is_express' => false,
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $mpl->id, 'weight_from_grams' => 5001],
            [
                'weight_from_grams' => 5001,
                'weight_to_grams' => null,
                'price_huf' => 3130,
                'is_express' => false,
            ]
        );

        // Foxpost csomagautomata (ALAPÉRTELMEZETT)
        $foxpost = ShippingMethod::updateOrCreate(
            ['provider' => 'foxpost', 'type' => 'parcel_locker'],
            [
                'name' => 'Foxpost csomagautomata',
                'type' => 'parcel_locker',
                'provider' => 'foxpost',
                'is_active' => true,
                'is_default' => true,
                'requires_address' => false,
                'requires_parcel_point' => true,
                'supports_cod' => true,
                'cod_fee_huf' => 400,
                'min_weight_grams' => null,
                'max_weight_grams' => 5000,
                'sort_order' => 2,
                'description' => 'Foxpost csomagautomata - válasszon csomagpontot',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $foxpost->id, 'weight_from_grams' => 0],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => null,
                'price_huf' => 1699,
                'is_express' => false,
            ]
        );

        // Packeta csomagpont
        $packeta = ShippingMethod::updateOrCreate(
            ['provider' => 'packeta', 'type' => 'parcel_locker'],
            [
                'name' => 'Packeta csomagpont',
                'type' => 'parcel_locker',
                'provider' => 'packeta',
                'is_active' => true,
                'requires_address' => false,
                'requires_parcel_point' => true,
                'supports_cod' => true,
                'cod_fee_huf' => 400,
                'min_weight_grams' => null,
                'max_weight_grams' => 5000,
                'sort_order' => 3,
                'description' => 'Packeta csomagpont - válasszon csomagpontot',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $packeta->id, 'weight_from_grams' => 0],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => null,
                'price_huf' => 950,
                'is_express' => false,
            ]
        );

        // Magyar Posta levél - normál
        $postaNormal = ShippingMethod::updateOrCreate(
            ['provider' => 'magyar_posta', 'type' => 'letter', 'sort_order' => 5],
            [
                'name' => 'Magyar Posta levél',
                'type' => 'letter',
                'provider' => 'magyar_posta',
                'is_active' => true,
                'requires_address' => true,
                'requires_parcel_point' => false,
                'supports_cod' => false,
                'cod_fee_huf' => 0,
                'min_weight_grams' => null,
                'max_weight_grams' => 500,
                'sort_order' => 4,
                'description' => 'Postai levélként - max 500g, 2-4 munkanap',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $postaNormal->id, 'weight_from_grams' => 0, 'is_express' => false],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => 500,
                'price_huf' => 270,
                'is_express' => false,
            ]
        );

        // Magyar Posta levél - elsőbbségi
        $postaExpress = ShippingMethod::updateOrCreate(
            ['provider' => 'magyar_posta', 'type' => 'letter', 'sort_order' => 6],
            [
                'name' => 'Magyar Posta elsőbbségi levél',
                'type' => 'letter',
                'provider' => 'magyar_posta',
                'is_active' => true,
                'requires_address' => true,
                'requires_parcel_point' => false,
                'supports_cod' => false,
                'cod_fee_huf' => 0,
                'min_weight_grams' => null,
                'max_weight_grams' => 500,
                'sort_order' => 5,
                'description' => 'Elsőbbségi levél - max 500g, 1-2 munkanap',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $postaExpress->id, 'weight_from_grams' => 0, 'is_express' => true],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => 50,
                'price_huf' => 390,
                'is_express' => true,
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $postaExpress->id, 'weight_from_grams' => 51, 'is_express' => true],
            [
                'weight_from_grams' => 51,
                'weight_to_grams' => 500,
                'price_huf' => 1115,
                'is_express' => true,
            ]
        );

        // Személyes átvétel
        $pickup = ShippingMethod::updateOrCreate(
            ['provider' => 'pickup', 'type' => 'pickup'],
            [
                'name' => 'Személyes átvétel',
                'type' => 'pickup',
                'provider' => 'pickup',
                'is_active' => true,
                'requires_address' => false,
                'requires_parcel_point' => false,
                'supports_cod' => false,
                'cod_fee_huf' => 0,
                'min_weight_grams' => null,
                'max_weight_grams' => null,
                'sort_order' => 6,
                'description' => 'Személyes átvétel előre egyeztetett időpontban',
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $pickup->id, 'weight_from_grams' => 0],
            [
                'weight_from_grams' => 0,
                'weight_to_grams' => null,
                'price_huf' => 0,
                'is_express' => false,
            ]
        );

        $this->command->info('Shipping methods and rates seeded successfully');
    }
}
