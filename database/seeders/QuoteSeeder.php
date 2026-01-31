<?php

namespace Database\Seeders;

use App\Models\Quote;
use Illuminate\Database\Seeder;

/**
 * QuoteSeeder - Árajánlat minta adatok
 *
 * Létrehoz egy minta árajánlatot a rendszerben.
 */
class QuoteSeeder extends Seeder
{
    public function run(): void
    {
        Quote::updateOrCreate(
            ['quote_number' => 'AJ-2026-2MG8KU'],
            [
                'customer_name' => 'Nagyné Molnár Ildikó',
                'customer_title' => 'Tisztelt Nagyné Molnár Ildikó!',
                'customer_email' => 'nmildo@gmail.com',
                'customer_phone' => null,
                'quote_date' => '2026-01-08',
                'quote_type' => 'full_production',
                'size' => '70x100',
                'intro_text' => 'Köszönöm érdeklődését! 2012 óta készítek tablókat - az alábbiakban részletezem a {{size}} tabló árajánlatot.',
                'content_items' => [
                    [
                        'title' => 'Prémium nyomtatás',
                        'description' => 'UV-álló, élénk színek, amelyek évek múlva is ugyanolyan szépek maradnak.',
                    ],
                    [
                        'title' => 'Üvegezett fa keret',
                        'description' => 'Többféle stílus közül választható a tablóhoz.',
                    ],
                    [
                        'title' => 'Személyes kiszállítás',
                        'description' => 'Az ország bármely pontjára, előre egyeztetett időpontban. A szállítás díját külön egyeztetjük.',
                    ],
                ],
                'is_full_execution' => true,
                'has_small_tablo' => false,
                'has_shipping' => true,
                'has_production' => false,
                'base_price' => 70000,
                'discount_price' => 0,
                'small_tablo_price' => 0,
                'shipping_price' => 10000,
                'production_price' => 0,
                'small_tablo_text' => null,
                'production_text' => null,
                'discount_text' => null,
                'notes' => "**Az árak tartalmazzák az ÁFA értékét\n**Az ár tartalmazza a házhozszállítás díját: 10.000 Ft\n\nKérdés esetén bátran keressen - több mint 1000 osztállyal dolgoztam már együtt!",
            ]
        );

        $this->command->info('✅ Árajánlat minta létrehozva (AJ-2026-2MG8KU)');
    }
}
