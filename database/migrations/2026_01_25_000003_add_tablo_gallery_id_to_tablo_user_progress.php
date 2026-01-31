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
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            // Add tablo_gallery_id as the primary reference (NEW architecture)
            $table->foreignId('tablo_gallery_id')
                ->nullable()
                ->after('user_id')
                ->constrained('tablo_galleries')
                ->nullOnDelete();

            // Keep work_session_id nullable for backward compatibility (LEGACY)
            // BUT new records will use tablo_gallery_id instead
            $table->foreignId('work_session_id')->nullable()->change();
            $table->foreignId('child_work_session_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            $table->dropForeign(['tablo_gallery_id']);
            $table->dropColumn('tablo_gallery_id');

            // Restore work_session_id as required
            $table->foreignId('work_session_id')->nullable(false)->change();
        });
    }
};
