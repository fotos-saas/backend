<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->integer('default_gallery_deadline_days')->nullable()->default(14)->after('default_max_retouch_photos');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn('default_gallery_deadline_days');
        });
    }
};
