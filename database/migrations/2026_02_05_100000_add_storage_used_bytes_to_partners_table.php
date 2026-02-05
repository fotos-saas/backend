<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->bigInteger('storage_used_bytes')->nullable()->after('additional_storage_gb');
            $table->timestamp('storage_calculated_at')->nullable()->after('storage_used_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['storage_used_bytes', 'storage_calculated_at']);
        });
    }
};
