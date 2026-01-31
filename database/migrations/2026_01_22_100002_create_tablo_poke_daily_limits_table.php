<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_poke_daily_limits', function (Blueprint $table) {
            $table->id();

            // Ki küldte
            $table->foreignId('from_guest_session_id')
                ->constrained('tablo_guest_sessions')
                ->cascadeOnDelete();

            // Dátum (csak nap, idő nélkül)
            $table->date('date');

            // Aznap küldött bökések száma
            $table->integer('pokes_sent')->default(0);

            $table->timestamps();

            // Egyedi kulcs: egy felhasználó egy napra
            $table->unique(['from_guest_session_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_poke_daily_limits');
    }
};
