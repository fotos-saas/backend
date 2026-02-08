<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_albums', function (Blueprint $table) {
            $table->string('webshop_share_token', 32)->nullable()->unique()->after('allow_download');
        });

        Schema::table('tablo_galleries', function (Blueprint $table) {
            $table->string('webshop_share_token', 32)->nullable()->unique()->after('max_retouch_photos');
        });
    }

    public function down(): void
    {
        Schema::table('partner_albums', function (Blueprint $table) {
            $table->dropColumn('webshop_share_token');
        });

        Schema::table('tablo_galleries', function (Blueprint $table) {
            $table->dropColumn('webshop_share_token');
        });
    }
};
