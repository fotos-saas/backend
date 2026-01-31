<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kliens regisztráció funkció - adatbázis bővítés
 *
 * Ez a migráció hozzáadja az email/jelszó alapú bejelentkezés és
 * regisztráció támogatásához szükséges mezőket.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Partner kliensek bővítése auth mezőkkel
        Schema::table('partner_clients', function (Blueprint $table) {
            // Jelszó tárolás (nullable - opcionális regisztráció)
            $table->string('password')->nullable()->after('phone');

            // Regisztráció státusz - ha true, CSAK jelszóval léphet be (kód megszűnik)
            $table->boolean('is_registered')->default(false)->after('password');

            // Regisztráció időpontja
            $table->timestamp('registered_at')->nullable()->after('is_registered');

            // Email megerősítés (opcionális, későbbi fejlesztéshez)
            $table->timestamp('email_verified_at')->nullable()->after('registered_at');

            // Értesítések fogadása
            $table->boolean('wants_notifications')->default(true)->after('email_verified_at');

            // Remember token a "maradj bejelentkezve" funkcióhoz
            $table->rememberToken()->after('wants_notifications');
        });

        // Partner albumok bővítése regisztráció és letöltés beállításokkal
        Schema::table('partner_albums', function (Blueprint $table) {
            // Partner engedélyezi-e a regisztrációt az ügyfélnek
            $table->boolean('allow_registration')->default(false)->after('settings');

            // Letöltés hány napig elérhető a véglegesítés után (null = korlátlan)
            $table->unsignedInteger('download_days')->nullable()->after('allow_registration');
        });

        // Index a gyorsabb kereséshez
        Schema::table('partner_clients', function (Blueprint $table) {
            $table->index('is_registered');
            $table->index('email'); // Email alapú bejelentkezéshez
        });
    }

    public function down(): void
    {
        Schema::table('partner_clients', function (Blueprint $table) {
            $table->dropIndex(['is_registered']);
            $table->dropIndex(['email']);

            $table->dropRememberToken();
            $table->dropColumn([
                'password',
                'is_registered',
                'registered_at',
                'email_verified_at',
                'wants_notifications',
            ]);
        });

        Schema::table('partner_albums', function (Blueprint $table) {
            $table->dropColumn([
                'allow_registration',
                'download_days',
            ]);
        });
    }
};
