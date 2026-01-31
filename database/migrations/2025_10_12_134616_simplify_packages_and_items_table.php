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
        // Add price and selectable_photos_count to packages table
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('price')->unsigned()->nullable()->after('name');
            $table->integer('selectable_photos_count')->unsigned()->default(1)->after('price');
        });

        // Simplify package_items table
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropColumn(['max_qty', 'discount_percent', 'custom_price']);
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->renameColumn('min_qty', 'quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse package_items changes
        Schema::table('package_items', function (Blueprint $table) {
            $table->renameColumn('quantity', 'min_qty');
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->integer('max_qty')->unsigned()->nullable()->after('min_qty');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('max_qty');
            $table->integer('custom_price')->unsigned()->nullable()->after('discount_percent');
        });

        // Reverse packages changes
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price', 'selectable_photos_count']);
        });
    }
};
