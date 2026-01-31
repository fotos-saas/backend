<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reakció típus hozzáadása a tablo_post_likes táblához.
 *
 * Támogatott reakciók: 💀 😭 🫡 ❤️ 👀
 * Default: ❤️ (visszafelé kompatibilitás)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_post_likes', function (Blueprint $table) {
            $table->string('reaction', 10)->default('❤️')->after('liker_id');
        });

        // Unique constraint frissítése: egy user egy post-ra EGY reakciót adhat (bármilyet)
        // Megtartjuk a régi unique-ot (post + liker), mert egy user csak 1 reakciót adhat
        // Nem kell változtatni, a régi constraint jó
    }

    public function down(): void
    {
        Schema::table('tablo_post_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
