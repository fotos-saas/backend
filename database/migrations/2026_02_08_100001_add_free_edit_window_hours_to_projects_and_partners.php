<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('free_edit_window_hours')
                ->nullable()
                ->after('max_retouch_photos')
                ->comment('null = partner default');
        });

        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_free_edit_window_hours')
                ->nullable()
                ->default(24)
                ->after('default_gallery_deadline_days');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn('free_edit_window_hours');
        });

        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn('default_free_edit_window_hours');
        });
    }
};
