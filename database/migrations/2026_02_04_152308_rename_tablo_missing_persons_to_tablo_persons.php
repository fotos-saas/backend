<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Átnevezi a tablo_missing_persons táblát tablo_persons-ra,
     * és a tablo_guest_sessions.tablo_missing_person_id oszlopot tablo_person_id-ra.
     */
    public function up(): void
    {
        // 1. Tábla átnevezése
        Schema::rename('tablo_missing_persons', 'tablo_persons');

        // 2. Foreign key oszlop átnevezése a tablo_guest_sessions táblában
        Schema::table('tablo_guest_sessions', function ($table) {
            $table->renameColumn('tablo_missing_person_id', 'tablo_person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Foreign key oszlop visszanevezése
        Schema::table('tablo_guest_sessions', function ($table) {
            $table->renameColumn('tablo_person_id', 'tablo_missing_person_id');
        });

        // 2. Tábla visszanevezése
        Schema::rename('tablo_persons', 'tablo_missing_persons');
    }
};
