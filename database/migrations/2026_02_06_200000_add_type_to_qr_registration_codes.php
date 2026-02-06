<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_registration_codes', function (Blueprint $table) {
            $table->string('type', 20)->default('coordinator')->after('code');
            $table->boolean('is_pinned')->default(false)->after('max_usages');

            $table->index(['tablo_project_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_registration_codes', function (Blueprint $table) {
            $table->dropIndex(['tablo_project_id', 'type', 'is_active']);
            $table->dropColumn(['type', 'is_pinned']);
        });
    }
};
