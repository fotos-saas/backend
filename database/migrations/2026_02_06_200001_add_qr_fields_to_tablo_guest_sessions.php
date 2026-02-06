<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->foreignId('qr_registration_code_id')
                ->nullable()
                ->after('tablo_person_id')
                ->constrained('qr_registration_codes')
                ->nullOnDelete();
            $table->string('registration_type', 20)->nullable()->after('qr_registration_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropForeign(['qr_registration_code_id']);
            $table->dropColumn(['qr_registration_code_id', 'registration_type']);
        });
    }
};
