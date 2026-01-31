<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendég azonosítás és ütközéskezelés bővítés.
     *
     * Új mezők:
     * - tablo_missing_person_id: Párosítás a tablón szereplő személyhez
     * - verification_status: Verifikáció státusz ütközéskezeléshez
     */
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            // Párosítás a tablón szereplő személyhez
            $table->foreignId('tablo_missing_person_id')
                ->nullable()
                ->after('is_coordinator')
                ->constrained('tablo_missing_persons')
                ->nullOnDelete();

            // Verifikáció státusz - ütközéskezeléshez
            // verified: automatikusan jóváhagyva (nem volt ütközés)
            // pending: ügyintéző döntésére vár (ütközés volt)
            // rejected: elutasítva (nem ő az a személy)
            $table->string('verification_status', 20)
                ->default('verified')
                ->after('tablo_missing_person_id');

            // Indexek a gyors lekérdezésekhez
            $table->index('tablo_missing_person_id', 'tgs_missing_person_idx');
            $table->index('verification_status', 'tgs_verification_status_idx');

            // Unique constraint: egy projekt egy személyhez csak egy verified session tartozhat
            // Pending státusznál megengedett a duplikáció (ütközés esetén)
            // Ez az index nem fogja blokkolni a pending-eket
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropForeign(['tablo_missing_person_id']);
            $table->dropIndex('tgs_missing_person_idx');
            $table->dropIndex('tgs_verification_status_idx');
            $table->dropColumn(['tablo_missing_person_id', 'verification_status']);
        });
    }
};
