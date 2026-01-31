<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reakció típus hozzáadása a tablo_newsfeed_likes táblához.
 *
 * Támogatott reakciók: 💀 😭 🫡 ❤️ 👀
 * Default: ❤️ (visszafelé kompatibilitás)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_newsfeed_likes', function (Blueprint $table) {
            $table->string('reaction', 10)->default('❤️')->after('liker_id');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_newsfeed_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
