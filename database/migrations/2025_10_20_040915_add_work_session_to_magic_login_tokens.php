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
        Schema::table('magic_login_tokens', function (Blueprint $table) {
            $table->foreignId('work_session_id')->nullable()->after('user_id')->constrained('work_sessions')->nullOnDelete();
            $table->index('work_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_login_tokens', function (Blueprint $table) {
            $table->dropForeign(['work_session_id']);
            $table->dropColumn('work_session_id');
        });
    }
};
