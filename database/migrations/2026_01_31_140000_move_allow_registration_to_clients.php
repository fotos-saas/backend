<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regisztráció engedélyezés áthelyezése kliens szintre
 *
 * A regisztráció engedélyezés nem album szinten, hanem kliens szinten van.
 * A partner az ügyfél szerkesztésénél dönt erről.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hozzáadjuk a partner_clients táblához
        Schema::table('partner_clients', function (Blueprint $table) {
            $table->boolean('allow_registration')->default(false)->after('wants_notifications');
        });

        // Töröljük a partner_albums táblából (ha létezik)
        if (Schema::hasColumn('partner_albums', 'allow_registration')) {
            Schema::table('partner_albums', function (Blueprint $table) {
                $table->dropColumn('allow_registration');
            });
        }

        // A download_days marad az albumon (album szintű beállítás)
    }

    public function down(): void
    {
        // Visszatesszük az albumokra
        if (!Schema::hasColumn('partner_albums', 'allow_registration')) {
            Schema::table('partner_albums', function (Blueprint $table) {
                $table->boolean('allow_registration')->default(false)->after('settings');
            });
        }

        // Töröljük a kliensről
        Schema::table('partner_clients', function (Blueprint $table) {
            $table->dropColumn('allow_registration');
        });
    }
};
