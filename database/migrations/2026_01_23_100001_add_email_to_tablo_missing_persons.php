<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email mező hozzáadása a hiányzó személyekhez.
     *
     * Az email segít a párosításban, ha a vendég megadja regisztrációkor.
     */
    public function up(): void
    {
        Schema::table('tablo_missing_persons', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');

            // Unique constraint: egy projekten belül nem lehet két azonos email
            $table->unique(['tablo_project_id', 'email'], 'tablo_missing_persons_project_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_missing_persons', function (Blueprint $table) {
            $table->dropUnique('tablo_missing_persons_project_email_unique');
            $table->dropColumn('email');
        });
    }
};
