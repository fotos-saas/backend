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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // User Login
            $table->boolean('user_login_enabled')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Digit Code (6 számjegy)
            $table->boolean('digit_code_enabled')->default(false);
            $table->string('digit_code', 6)->nullable()->unique();
            $table->timestamp('digit_code_expires_at')->nullable();

            // Share Link
            $table->boolean('share_enabled')->default(false);
            $table->string('share_token')->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('digit_code');
            $table->index('share_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
