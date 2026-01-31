<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => 10,
            'enabled' => true,
            'expires_at' => now()->addYear(),
            'min_order_value' => 1000,
            'max_usage' => 100,
            'usage_count' => 0,
            'description' => '10% kedvezmény az első rendeléshez, minimum 1000 Ft rendelési érték felett',
        ]);

        Coupon::create([
            'code' => 'SUMMER20',
            'type' => 'percent',
            'value' => 20,
            'enabled' => true,
            'expires_at' => now()->addMonths(3),
            'min_order_value' => 5000,
            'max_usage' => 50,
            'usage_count' => 0,
            'description' => '20% nyári akció, minimum 5000 Ft rendelési érték felett',
        ]);

        Coupon::create([
            'code' => 'FIXED500',
            'type' => 'amount',
            'value' => 500,
            'enabled' => true,
            'expires_at' => now()->addMonths(6),
            'min_order_value' => 3000,
            'max_usage' => null, // Unlimited
            'usage_count' => 0,
            'description' => '500 Ft kedvezmény minden 3000 Ft feletti rendeléshez',
        ]);

        Coupon::create([
            'code' => 'BIRTHDAY15',
            'type' => 'percent',
            'value' => 15,
            'enabled' => true,
            'expires_at' => null, // No expiration
            'min_order_value' => 2000,
            'max_usage' => 200,
            'usage_count' => 5,
            'description' => '15% születésnapi kupon, minimum 2000 Ft rendelési érték felett',
        ]);

        Coupon::create([
            'code' => 'EXPIRED',
            'type' => 'percent',
            'value' => 50,
            'enabled' => false,
            'expires_at' => now()->subDays(10),
            'min_order_value' => 1000,
            'max_usage' => 10,
            'usage_count' => 10,
            'description' => 'Lejárt teszt kupon',
        ]);

        $this->command->info('✓ Created '.Coupon::count().' coupons');
    }
}
