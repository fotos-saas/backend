<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds login_method field to personal_access_tokens for tracking
     * how each token was created (password, code, magic_link, qr_registration).
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Login method that created this token
            $table->string('login_method', 50)
                ->nullable()
                ->after('work_session_id');

            // Additional metadata for session management
            $table->string('device_name')->nullable()->after('login_method');
            $table->string('ip_address', 45)->nullable()->after('device_name');

            // Index for session management queries
            $table->index('login_method');
            $table->index(['tokenable_type', 'tokenable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['login_method']);
            $table->dropIndex(['tokenable_type', 'tokenable_id', 'created_at']);

            $table->dropColumn([
                'login_method',
                'device_name',
                'ip_address',
            ]);
        });
    }
};
