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
        // Payment Methods Table
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['card', 'transfer', 'cash']);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        // Shipping Methods Table
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['courier', 'parcel_locker', 'letter', 'pickup']);
            $table->string('provider')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_address')->default(false);
            $table->boolean('requires_parcel_point')->default(false);
            $table->boolean('supports_cod')->default(false);
            $table->integer('cod_fee_huf')->default(0);
            $table->integer('min_weight_grams')->nullable();
            $table->integer('max_weight_grams')->nullable();
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('type');
            $table->index('provider');
        });

        // Shipping Rates Table
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->integer('weight_from_grams')->default(0);
            $table->integer('weight_to_grams')->nullable();
            $table->integer('price_huf');
            $table->boolean('is_express')->default(false);
            $table->timestamps();

            $table->index(['shipping_method_id', 'weight_from_grams']);
        });

        // Package Points Table
        Schema::create('package_points', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['foxpost', 'packeta']);
            $table->string('external_id');
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('zip');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->text('opening_hours')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('city');
            $table->index('zip');
            $table->index('is_active');
            $table->unique(['provider', 'external_id']);
        });

        // Add weight_grams to print_sizes table
        Schema::table('print_sizes', function (Blueprint $table) {
            $table->integer('weight_grams')->nullable()->after('height_mm');
        });

        // Add shipping and payment fields to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('coupon_id')->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->after('payment_method_id')->constrained('shipping_methods')->nullOnDelete();
            $table->foreignId('package_point_id')->nullable()->after('shipping_method_id')->constrained('package_points')->nullOnDelete();
            $table->text('shipping_address')->nullable()->after('package_point_id');
            $table->integer('shipping_cost_huf')->default(0)->after('shipping_address');
            $table->integer('cod_fee_huf')->default(0)->after('shipping_cost_huf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys from orders first
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['shipping_method_id']);
            $table->dropForeign(['package_point_id']);
            $table->dropColumn([
                'payment_method_id',
                'shipping_method_id',
                'package_point_id',
                'shipping_address',
                'shipping_cost_huf',
                'cod_fee_huf',
            ]);
        });

        // Remove weight_grams from print_sizes
        Schema::table('print_sizes', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });

        // Drop new tables in reverse order
        Schema::dropIfExists('package_points');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('payment_methods');
    }
};
