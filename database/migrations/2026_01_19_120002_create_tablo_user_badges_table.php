<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_user_badges', function (Blueprint $table) {
            $table->id();

            // Guest session kapcsolat
            $table->foreignId('tablo_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();

            // Badge kapcsolat
            $table->foreignId('tablo_badge_id')
                ->constrained('tablo_badges')
                ->cascadeOnDelete();

            // Megszerzés időpontja
            $table->timestamp('earned_at');

            // Új badge (még nem látta a user)
            $table->boolean('is_new')->default(true);

            // Megtekintés időpontja
            $table->timestamp('viewed_at')->nullable();

            $table->timestamps();

            // Egy session csak egyszer kaphatja meg ugyanazt a badge-et
            $table->unique(['tablo_guest_session_id', 'tablo_badge_id'], 'tablo_user_badges_unique');

            $table->index('earned_at');
            $table->index('is_new');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_user_badges');
    }
};
