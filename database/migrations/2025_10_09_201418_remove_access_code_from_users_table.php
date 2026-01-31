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
        // SQLite doesn't support dropping columns easily, so we'll skip this migration
        // The access_code columns are not needed for our current implementation
        if (config('database.default') === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'access_code')) {
                // Try to drop index if it exists (ignore errors)
                try {
                    $table->dropIndex(['access_code']);
                } catch (Exception $e) {
                    // Index might not exist, ignore
                }
                $table->dropColumn(['access_code', 'access_code_expires_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_code', 6)->nullable()->unique()->after('password');
            $table->timestamp('access_code_expires_at')->nullable()->after('access_code');
            $table->index('access_code');
        });
    }
};
