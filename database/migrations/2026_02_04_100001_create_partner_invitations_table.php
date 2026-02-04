<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Partner meghívások tábla.
     * Meghívó típusok:
     * - team_member: Munkatárs (grafikus, marketinges, nyomdász, ügyintéző)
     * - partner: Partner kapcsolat (fotós↔nyomda)
     *
     * Szerepkörök (team_member esetén):
     * - designer: Grafikus
     * - marketer: Marketinges
     * - printer: Nyomdász
     * - assistant: Ügyintéző
     *
     * Szerepkörök (partner esetén):
     * - photo_studio: Fotós partner meghívása
     * - print_shop: Nyomda partner meghívása
     */
    public function up(): void
    {
        Schema::create('partner_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->onDelete('cascade');
            $table->string('code', 12)->unique();
            $table->string('email');
            $table->string('type', 20)->default('team_member');
            $table->string('role', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['code', 'status']);
            $table->index(['email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_invitations');
    }
};
