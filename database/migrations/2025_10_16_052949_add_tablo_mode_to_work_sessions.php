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
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->boolean('is_tablo_mode')->default(false)->after('status');
            $table->integer('max_retouch_photos')->nullable()->after('is_tablo_mode');
            $table->foreignId('parent_work_session_id')->nullable()
                ->after('max_retouch_photos')
                ->constrained('work_sessions')
                ->nullOnDelete();

            $table->index('parent_work_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropForeign(['parent_work_session_id']);
            $table->dropColumn(['is_tablo_mode', 'max_retouch_photos', 'parent_work_session_id']);
        });
    }
};
