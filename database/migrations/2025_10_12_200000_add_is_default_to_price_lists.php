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
        Schema::table('price_lists', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('name');
        });

        // Set the first price list as default if exists
        $firstPriceList = \App\Models\PriceList::first();
        if ($firstPriceList) {
            $firstPriceList->update(['is_default' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
