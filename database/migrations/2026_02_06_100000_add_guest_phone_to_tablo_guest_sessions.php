<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->string('guest_phone')->nullable()->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropColumn('guest_phone');
        });
    }
};
