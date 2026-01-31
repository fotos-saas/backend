<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('has_production')->default(false)->after('has_shipping');
            $table->integer('production_price')->default(0)->after('shipping_price');
            $table->text('production_text')->nullable()->after('small_tablo_text');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['has_production', 'production_price', 'production_text']);
        });
    }
};
