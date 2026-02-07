<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->unsignedInteger('max_retouch_photos')
                ->nullable()
                ->default(null)
                ->after('max_template_selections');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn('max_retouch_photos');
        });
    }
};
