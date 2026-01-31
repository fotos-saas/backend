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
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('work_session_id')->nullable()->after('user_id')->constrained('work_sessions')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->after('work_session_id')->constrained('packages')->nullOnDelete();
            $table->string('session_token', 64)->nullable()->after('status')->index();
            $table->timestamp('expires_at')->nullable()->after('session_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['work_session_id']);
            $table->dropForeign(['package_id']);
            $table->dropColumn(['work_session_id', 'package_id', 'session_token', 'expires_at']);
        });
    }
};
