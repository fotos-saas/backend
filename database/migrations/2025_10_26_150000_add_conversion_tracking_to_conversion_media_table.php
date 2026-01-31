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
        Schema::table('conversion_media', function (Blueprint $table) {
            // Flag: Skip initial conversions on upload
            $table->boolean('skip_initial_conversions')
                ->default(false)
                ->after('conversion_status')
                ->comment('Ha true, feltöltéskor nem generál thumbnail-t');

            // Timestamp: Upload completed
            $table->timestamp('upload_completed_at')
                ->nullable()
                ->after('skip_initial_conversions')
                ->comment('Mikor fejeződött be a fájl feltöltés');

            // Timestamp: Conversion started
            $table->timestamp('conversion_started_at')
                ->nullable()
                ->after('upload_completed_at')
                ->comment('Mikor indult el a thumbnail generálás');

            // Timestamp: Conversion completed
            $table->timestamp('conversion_completed_at')
                ->nullable()
                ->after('conversion_started_at')
                ->comment('Mikor fejeződött be a thumbnail generálás');
        });

        // Index for filtering by conversion phase
        Schema::table('conversion_media', function (Blueprint $table) {
            $table->index(['conversion_job_id', 'conversion_status'], 'idx_job_status');
            $table->index('upload_completed_at', 'idx_upload_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversion_media', function (Blueprint $table) {
            $table->dropIndex('idx_job_status');
            $table->dropIndex('idx_upload_completed');

            $table->dropColumn([
                'skip_initial_conversions',
                'upload_completed_at',
                'conversion_started_at',
                'conversion_completed_at',
            ]);
        });
    }
};
