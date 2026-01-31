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
        Schema::table('conversion_jobs', function (Blueprint $table) {
            // Status index - gyakori WHERE status = 'X' query-k
            $table->index('status', 'idx_conversion_jobs_status');

            // Composite index: status + created_at - admin dashboard listákhoz
            $table->index(['status', 'created_at'], 'idx_conversion_jobs_status_created');

            // Created_at index - időrendi listázáshoz
            $table->index('created_at', 'idx_conversion_jobs_created_at');
        });

        Schema::table('conversion_media', function (Blueprint $table) {
            // Conversion status index - gyakori WHERE conversion_status = 'X' query-k
            $table->index('conversion_status', 'idx_conversion_media_status');

            // Composite index: conversion_job_id + conversion_status
            // Gyakori query: "Get all pending/failed media for job X"
            $table->index(['conversion_job_id', 'conversion_status'], 'idx_conversion_media_job_status');

            // Composite index: conversion_status + created_at - monitoring célokra
            $table->index(['conversion_status', 'created_at'], 'idx_conversion_media_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversion_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_conversion_jobs_status');
            $table->dropIndex('idx_conversion_jobs_status_created');
            $table->dropIndex('idx_conversion_jobs_created_at');
        });

        Schema::table('conversion_media', function (Blueprint $table) {
            $table->dropIndex('idx_conversion_media_status');
            $table->dropIndex('idx_conversion_media_job_status');
            $table->dropIndex('idx_conversion_media_status_created');
        });
    }
};
