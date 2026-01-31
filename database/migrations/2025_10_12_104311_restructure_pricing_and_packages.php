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
        // Update print_sizes table
        Schema::table('print_sizes', function (Blueprint $table) {
            $table->renameColumn('code', 'name');
        });

        Schema::table('print_sizes', function (Blueprint $table) {
            $table->integer('width_mm')->unsigned()->nullable()->change();
            $table->integer('height_mm')->unsigned()->nullable()->change();
        });

        // Update prices table
        Schema::table('prices', function (Blueprint $table) {
            $table->renameColumn('gross_huf', 'price');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn('digital_price_huf');
        });

        // Update price_lists table
        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn('active');
        });

        // Update package_items table
        Schema::table('package_items', function (Blueprint $table) {
            $table->renameColumn('qty', 'min_qty');
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->integer('min_qty')->unsigned()->nullable()->change();
            $table->integer('max_qty')->unsigned()->nullable()->after('min_qty');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('max_qty');
            $table->integer('custom_price')->unsigned()->nullable()->after('discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse package_items table
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropColumn(['max_qty', 'discount_percent', 'custom_price']);
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->integer('min_qty')->unsigned()->change();
            $table->renameColumn('min_qty', 'qty');
        });

        // Reverse price_lists table
        Schema::table('price_lists', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('album_id');
        });

        // Reverse prices table
        Schema::table('prices', function (Blueprint $table) {
            $table->integer('digital_price_huf')->unsigned()->nullable()->after('price');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->renameColumn('price', 'gross_huf');
        });

        // Reverse print_sizes table
        Schema::table('print_sizes', function (Blueprint $table) {
            $table->integer('width_mm')->unsigned()->change();
            $table->integer('height_mm')->unsigned()->change();
        });

        Schema::table('print_sizes', function (Blueprint $table) {
            $table->renameColumn('name', 'code');
        });
    }
};
