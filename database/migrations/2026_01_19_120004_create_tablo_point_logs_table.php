<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_point_logs', function (Blueprint $table) {
            $table->id();

            // Guest session kapcsolat
            $table->foreignId('tablo_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();

            // Tevékenység típusa
            $table->enum('action', ['post', 'reply', 'like_received', 'like_given', 'badge'])
                ->comment('Tevékenység típusa');

            // Pont változás
            $table->integer('points')->comment('Pont változás (+/-)');

            // Kapcsolódó model (TablodiscussionPost, TabloPostLike, stb.)
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            // Leírás
            $table->text('description')->nullable();

            $table->timestamp('created_at');

            // Indexek
            $table->index('action');
            $table->index('created_at');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_point_logs');
    }
};
