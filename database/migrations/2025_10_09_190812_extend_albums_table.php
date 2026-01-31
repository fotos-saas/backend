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
        Schema::table('albums', function (Blueprint $table) {
            $table->string('name')->nullable()->after('title');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('class_id');
            $table->date('date')->nullable()->after('user_id');
            $table->enum('status', ['active', 'archived', 'draft'])->default('active')->after('date');
            $table->json('flags')->nullable()->after('visibility');
            $table->string('thumbnail')->nullable()->after('flags');

            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['name', 'user_id', 'date', 'status', 'flags', 'thumbnail']);
        });
    }
};
