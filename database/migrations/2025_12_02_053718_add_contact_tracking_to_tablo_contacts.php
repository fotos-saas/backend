<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->unsignedInteger('call_count')->default(0)->after('note');
            $table->unsignedInteger('sms_count')->default(0)->after('call_count');
            $table->timestamp('last_contacted_at')->nullable()->after('sms_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->dropColumn(['call_count', 'sms_count', 'last_contacted_at']);
        });
    }
};
