<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Work session and package relationships
            $table->foreignId('work_session_id')->nullable()->after('user_id')->constrained('work_sessions')->onDelete('set null');
            $table->foreignId('package_id')->nullable()->after('work_session_id')->constrained('packages')->onDelete('set null');
            $table->foreignId('coupon_id')->nullable()->after('package_id')->constrained('coupons')->onDelete('set null');

            // Pricing details
            $table->integer('coupon_discount')->nullable()->after('coupon_id')->comment('Coupon discount amount in HUF');
            $table->integer('subtotal_huf')->after('coupon_discount')->comment('Subtotal before discount');
            $table->integer('discount_huf')->default(0)->after('subtotal_huf')->comment('Total discount amount');

            // Guest customer data
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->text('guest_address')->nullable()->after('guest_phone');

            // Update status column to support new statuses
            // Note: status column already exists with values: draft, submitted, payment_pending, paid, in_production, fulfilled, delivered, cancelled, refunded
            // We're adding: pending, processing, shipped, completed
            // The migration will preserve existing data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['work_session_id']);
            $table->dropForeign(['package_id']);
            $table->dropForeign(['coupon_id']);

            $table->dropColumn([
                'work_session_id',
                'package_id',
                'coupon_id',
                'coupon_discount',
                'subtotal_huf',
                'discount_huf',
                'guest_name',
                'guest_email',
                'guest_phone',
                'guest_address',
            ]);
        });
    }
};
