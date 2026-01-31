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
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_code', 6)->nullable()->unique()->after('password');
            $table->timestamp('access_code_expires_at')->nullable()->after('access_code');
            $table->json('address')->nullable()->after('phone');

            $table->index('access_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['access_code']);
            $table->dropColumn(['access_code', 'access_code_expires_at', 'address']);
        });
    }
};
