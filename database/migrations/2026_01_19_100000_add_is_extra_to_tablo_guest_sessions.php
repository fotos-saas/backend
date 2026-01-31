<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->boolean('is_extra')->default(false)->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_guest_sessions', function (Blueprint $table) {
            $table->dropColumn('is_extra');
        });
    }
};
