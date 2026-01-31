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
        Schema::table('tablo_notes', function (Blueprint $table) {
            $table->string('status')->default('new')->after('content');
            $table->foreignId('resolved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_notes', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['status', 'resolved_by', 'resolved_at']);
        });
    }
};
