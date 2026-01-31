<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the login_audits table for tracking all login attempts.
     * This is essential for security monitoring and compliance.
     */
    public function up(): void
    {
        Schema::create('login_audits', function (Blueprint $table) {
            $table->id();

            // User reference (nullable for failed attempts with unknown users)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Email used in attempt (for tracking failed attempts)
            $table->string('email')->nullable();

            // Login method: password, code, magic_link, qr_registration
            $table->string('login_method', 50);

            // Success/failure
            $table->boolean('success')->default(false);

            // Request details
            $table->string('ip_address', 45); // IPv6 compatible
            $table->text('user_agent')->nullable();

            // Failure reason (if applicable)
            $table->string('failure_reason')->nullable();

            // Additional metadata (JSON for flexibility)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for security analysis
            $table->index('user_id');
            $table->index('email');
            $table->index('ip_address');
            $table->index('success');
            $table->index('login_method');
            $table->index('created_at');

            // Composite indexes for common queries
            $table->index(['email', 'success', 'created_at']);
            $table->index(['ip_address', 'success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_audits');
    }
};
