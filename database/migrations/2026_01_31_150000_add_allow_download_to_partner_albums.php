<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letöltés engedélyezése album szinten
 * 
 * A partner albumonként dönt, hogy az ügyfél letöltheti-e a kiválasztott képeit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_albums', function (Blueprint $table) {
            $table->boolean('allow_download')->default(false)->after('download_days');
        });
    }

    public function down(): void
    {
        Schema::table('partner_albums', function (Blueprint $table) {
            $table->dropColumn('allow_download');
        });
    }
};
