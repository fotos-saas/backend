<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            $table->unsignedSmallInteger('modification_count')
                ->default(0)
                ->after('finalized_at');
            $table->timestamp('last_modification_paid_at')
                ->nullable()
                ->after('modification_count');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            $table->dropColumn(['modification_count', 'last_modification_paid_at']);
        });
    }
};
