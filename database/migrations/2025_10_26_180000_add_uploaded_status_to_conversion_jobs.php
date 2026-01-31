<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL: Drop and recreate check constraint with new 'uploaded' status
        DB::statement("
            ALTER TABLE conversion_jobs
            DROP CONSTRAINT IF EXISTS conversion_jobs_status_check
        ");

        DB::statement("
            ALTER TABLE conversion_jobs
            ADD CONSTRAINT conversion_jobs_status_check
            CHECK (status IN ('pending', 'uploading', 'uploaded', 'converting', 'completed', 'failed'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original constraint (without 'uploaded')
        DB::statement("
            ALTER TABLE conversion_jobs
            DROP CONSTRAINT IF EXISTS conversion_jobs_status_check
        ");

        DB::statement("
            ALTER TABLE conversion_jobs
            ADD CONSTRAINT conversion_jobs_status_check
            CHECK (status IN ('pending', 'uploading', 'converting', 'completed', 'failed'))
        ");
    }
};
