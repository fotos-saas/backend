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
        // First, update all NULL values to 5
        DB::statement('UPDATE work_sessions SET max_retouch_photos = 5 WHERE max_retouch_photos IS NULL');

        // Then change the column to have a default value of 5 and make it not nullable
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->integer('max_retouch_photos')->default(5)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->integer('max_retouch_photos')->nullable()->change();
        });
    }
};
