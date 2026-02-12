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
        if (!Schema::hasColumn('tablo_projects', 'work_session_id')) {
            return;
        }

        Schema::table('tablo_projects', function (Blueprint $table) {
            // Foreign key may not exist on fresh migrations
            try {
                $table->dropForeign(['work_session_id']);
            } catch (\Exception $e) {
                // Ignore if constraint doesn't exist
            }
            $table->dropColumn('work_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->foreignId('work_session_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('work_sessions')
                ->nullOnDelete();
        });
    }
};
