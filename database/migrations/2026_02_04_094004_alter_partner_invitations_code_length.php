<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix: code oszlop méretének növelése 12-ről 20-ra.
     *
     * Probléma: INVITE-XXXXXX formátum = 13 karakter,
     * de az oszlop csak 12-t engedélyezett.
     */
    public function up(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_invitations', function (Blueprint $table) {
            $table->string('code', 12)->change();
        });
    }
};
