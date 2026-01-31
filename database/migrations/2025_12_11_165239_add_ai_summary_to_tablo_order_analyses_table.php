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
        Schema::table('tablo_order_analyses', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('special_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_order_analyses', function (Blueprint $table) {
            $table->dropColumn('ai_summary');
        });
    }
};
