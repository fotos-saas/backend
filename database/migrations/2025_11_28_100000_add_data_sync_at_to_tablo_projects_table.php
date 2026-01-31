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
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->jsonb('data')->nullable()->after('is_aware');
            $table->timestamp('sync_at')->nullable()->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn(['data', 'sync_at']);
        });
    }
};
