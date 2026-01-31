<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_pokes', function (Blueprint $table) {
            $table->id();

            // Ki küldte - tablo_guest_sessions táblából
            $table->foreignId('from_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();

            // Kinek - tablo_guest_sessions táblából
            $table->foreignId('target_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();

            // Melyik projektben
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();

            // Kategória: mire hiányzik a felhasználó
            $table->enum('category', ['voting', 'photoshoot', 'image_selection', 'general'])
                ->default('general');

            // Üzenet típus: preset vagy egyéni
            $table->enum('message_type', ['preset', 'custom'])
                ->default('preset');

            // Ha preset üzenet
            $table->string('preset_key')->nullable();

            // Ha egyéni üzenet
            $table->string('custom_message', 500)->nullable();

            // Megjelenítéshez (cache-elve a preset-ből vagy custom)
            $table->string('emoji', 10)->nullable();
            $table->string('text', 500)->nullable();

            // Státusz
            $table->enum('status', ['sent', 'pending', 'resolved', 'expired'])
                ->default('sent');

            // Reakció: 💀 | 😭 | 🫡 | ❤️ | 👀
            $table->string('reaction', 10)->nullable();

            // Olvasva
            $table->boolean('is_read')->default(false);

            // Időbélyegek
            $table->timestamp('reacted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Indexek
            $table->index(['tablo_project_id', 'target_guest_session_id']);
            $table->index(['from_guest_session_id', 'created_at']);
            $table->index(['target_guest_session_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_pokes');
    }
};
