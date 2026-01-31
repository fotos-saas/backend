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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('tablo_project_id')
                ->nullable()
                ->after('work_session_id')
                ->constrained('tablo_projects')
                ->nullOnDelete();

            $table->index('tablo_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['tablo_project_id']);
            $table->dropIndex(['tablo_project_id']);
            $table->dropColumn('tablo_project_id');
        });
    }
};
