<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guest session restore token támogatás - Magic link email küldéshez
     */
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->string('restore_token', 64)->nullable()->unique()->after('verification_status');
            $table->timestamp('restore_token_expires_at')->nullable()->after('restore_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropColumn(['restore_token', 'restore_token_expires_at']);
        });
    }
};
