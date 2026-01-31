<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_notifications', function (Blueprint $table) {
            $table->id();

            // Projekt kapcsolat
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();

            // Címzett (polymorphic: contact vagy guest)
            $table->string('recipient_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('recipient_id');

            // Értesítés típusa
            $table->string('type'); // 'mention', 'reply', 'like', 'badge'

            // Tartalom
            $table->string('title');
            $table->text('body');

            // Kontextus adatok (JSON)
            $table->json('data')->nullable();

            // Forrás entitás (polymorphic)
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // Állapot
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Link
            $table->string('action_url')->nullable();

            $table->timestamps();

            // Indexek
            $table->index(['recipient_type', 'recipient_id', 'is_read', 'created_at'], 'tablo_notif_recipient_read_idx');
            $table->index(['type', 'created_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_notifications');
    }
};
