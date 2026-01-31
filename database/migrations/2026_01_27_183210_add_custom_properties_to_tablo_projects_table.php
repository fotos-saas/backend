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
            $table->jsonb('custom_properties')->nullable()->after('tablo_gallery_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn('custom_properties');
        });
    }
};
