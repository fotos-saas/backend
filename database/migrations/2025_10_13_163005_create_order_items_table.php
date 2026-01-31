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
        Schema::table('order_items', function (Blueprint $table) {
            // Add new columns for Stripe checkout compatibility
            $table->string('size')->nullable()->after('photo_id')->comment('Print size, e.g. 10x15, 13x18');
            $table->integer('total_price_huf')->after('unit_price_gross_huf')->comment('Total price (quantity × unit_price_huf)');
        });

        // Rename columns in separate statement
        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('qty', 'quantity');
            $table->renameColumn('unit_price_gross_huf', 'unit_price_huf');
        });

        // Make photo_id nullable
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('photo_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['size', 'total_price_huf']);
            $table->renameColumn('quantity', 'qty');
            $table->renameColumn('unit_price_huf', 'unit_price_gross_huf');
        });
    }
};
