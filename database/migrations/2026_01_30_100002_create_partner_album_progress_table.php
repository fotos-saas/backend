<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_album_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_album_id')
                ->constrained('partner_albums')
                ->cascadeOnDelete();
            $table->foreignId('partner_client_id')
                ->constrained('partner_clients')
                ->cascadeOnDelete();
            $table->enum('current_step', ['claiming', 'retouch', 'tablo'])->default('claiming');
            $table->json('steps_data')->nullable();
            $table->timestamps();

            $table->unique(['partner_album_id', 'partner_client_id']);
            $table->index('partner_album_id');
            $table->index('partner_client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_album_progress');
    }
};
