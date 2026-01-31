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
        Schema::table('email_templates', function (Blueprint $table) {
            $table->foreignId('smtp_account_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal')->after('smtp_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropForeign(['smtp_account_id']);
            $table->dropColumn(['smtp_account_id', 'priority']);
        });
    }
};
