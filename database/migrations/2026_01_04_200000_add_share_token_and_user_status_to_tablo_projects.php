<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds:
     * 1. Share token fields for public link sharing (no code required)
     * 2. User status field for customer-facing status display
     * 3. Admin preview token for one-time preview links
     * 4. Project access logs table for audit trail
     */
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            // Share token for public link sharing (kód nélküli megosztás)
            $table->boolean('share_token_enabled')->default(true)->after('access_code_expires_at');
            $table->string('share_token', 64)->nullable()->unique()->after('share_token_enabled');
            $table->timestamp('share_token_expires_at')->nullable()->after('share_token');

            // User status - customer-facing status message
            $table->string('user_status')->nullable()->after('share_token_expires_at');
            $table->string('user_status_color')->nullable()->after('user_status');

            // Admin preview token - one-time use for admin preview
            $table->string('admin_preview_token', 64)->nullable()->unique()->after('user_status_color');
            $table->timestamp('admin_preview_token_expires_at')->nullable()->after('admin_preview_token');
        });

        // Project access logs table for audit trail
        Schema::create('tablo_project_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')->constrained()->cascadeOnDelete();
            $table->string('access_type'); // 'code', 'share_token', 'admin_preview'
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Extra data like browser info
            $table->timestamps();

            $table->index(['tablo_project_id', 'created_at']);
            $table->index('access_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_project_access_logs');

        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn([
                'share_token_enabled',
                'share_token',
                'share_token_expires_at',
                'user_status',
                'user_status_color',
                'admin_preview_token',
                'admin_preview_token_expires_at',
            ]);
        });
    }
};
