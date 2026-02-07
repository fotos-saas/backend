<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->unsignedInteger('default_max_retouch_photos')
                ->nullable()
                ->default(3)
                ->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn('default_max_retouch_photos');
        });
    }
};
