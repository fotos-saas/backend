<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            // Pontrendszer
            $table->integer('points')->default(0)->after('avatar_url')
                ->comment('Összesített pontszám');

            // Rang
            $table->integer('rank_level')->default(1)->after('points')
                ->comment('Aktuális rang szint (1-6)');

            // Cache a gyorsabb lekérdezéshez
            $table->integer('posts_count')->default(0)->after('rank_level')
                ->comment('Hozzászólások száma (cache)');
            $table->integer('replies_count')->default(0)->after('posts_count')
                ->comment('Válaszok száma (cache)');
            $table->integer('likes_received')->default(0)->after('replies_count')
                ->comment('Kapott like-ok száma (cache)');
            $table->integer('likes_given')->default(0)->after('likes_received')
                ->comment('Adott like-ok száma (cache)');

            // Indexek
            $table->index('points');
            $table->index('rank_level');
            $table->index(['points', 'rank_level'], 'tablo_guest_leaderboard_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropIndex('tablo_guest_leaderboard_idx');
            $table->dropIndex(['tablo_guest_sessions_rank_level_index']);
            $table->dropIndex(['tablo_guest_sessions_points_index']);

            $table->dropColumn([
                'points',
                'rank_level',
                'posts_count',
                'replies_count',
                'likes_received',
                'likes_given',
            ]);
        });
    }
};
