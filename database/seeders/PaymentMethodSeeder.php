<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Bankkártya',
                'type' => 'card',
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Online bankkártyás fizetés (Stripe)',
                'icon' => 'credit-card',
            ],
            [
                'name' => 'Átutalás',
                'type' => 'transfer',
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Banki átutalás (rendelés visszaigazolása után)',
                'icon' => 'building-library',
            ],
            [
                'name' => 'Készpénz - utánvét',
                'type' => 'cash',
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'Fizetés átvételkor készpénzzel (utánvét díj felszámítása)',
                'icon' => 'banknotes',
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['type' => $method['type']],
                $method
            );
        }

        $this->command->info('Payment methods seeded successfully');
    }
}
