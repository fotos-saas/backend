<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_poll_id')
                ->constrained('tablo_polls')
                ->cascadeOnDelete();
            $table->foreignId('tablo_poll_option_id')
                ->constrained('tablo_poll_options')
                ->cascadeOnDelete();
            $table->foreignId('tablo_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();
            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(
                ['tablo_poll_id', 'tablo_guest_session_id', 'tablo_poll_option_id'],
                'tablo_poll_votes_unique'
            );
            $table->index(['tablo_poll_id', 'tablo_guest_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_poll_votes');
    }
};
