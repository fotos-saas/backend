<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Partner csapattagok pivot tábla.
     * Szabadúszó modell: egy user több partnerhez is tartozhat.
     *
     * Szerepkörök:
     * - designer: Grafikus (tablók tervezése)
     * - marketer: Marketinges (ügyfélkezelés)
     * - printer: Nyomdász (nyomtatás kezelése)
     * - assistant: Ügyintéző (irodai admin)
     */
    public function up(): void
    {
        Schema::create('partner_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['partner_id', 'user_id', 'role']);
            $table->index(['user_id', 'is_active']);
            $table->index(['partner_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_team_members');
    }
};
