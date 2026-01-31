<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds authentication-related fields to the users table:
     * - Account lockout protection (brute force prevention)
     * - Login tracking
     * - 2FA preparation (for future implementation)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account lockout (brute force prevention)
            $table->integer('failed_login_attempts')->default(0)->after('password_set');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            // Login tracking
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at'); // IPv6 compatible

            // 2FA preparation (fields added now, implementation later)
            $table->string('two_factor_secret')->nullable()->after('last_login_ip');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');

            // Indexes for performance
            $table->index('locked_until');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locked_until']);
            $table->dropIndex(['last_login_at']);

            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'last_login_at',
                'last_login_ip',
                'two_factor_secret',
                'two_factor_enabled',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
