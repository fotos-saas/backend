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
        Schema::create('smtp_accounts', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name');
            $table->string('mailer_type')->default('smtp'); // smtp, ses, postmark, sendmail, etc.

            // SMTP settings
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('encryption')->nullable(); // tls, ssl, null

            // From settings
            $table->string('from_address');
            $table->string('from_name');

            // Rate limiting
            $table->integer('rate_limit_per_minute')->nullable();
            $table->integer('rate_limit_per_hour')->nullable();

            // Priority & Environment
            $table->integer('priority')->default(5); // 1-10, 1 = highest
            $table->boolean('is_prod')->default(false);
            $table->boolean('is_active')->default(true);

            // Health monitoring
            $table->enum('health_status', ['healthy', 'warning', 'error', 'unchecked'])->default('unchecked');
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('health_error_message')->nullable();

            // Manual redirect/fallback
            $table->foreignId('manual_redirect_to')->nullable()->constrained('smtp_accounts')->nullOnDelete();

            // Anti-spam settings
            $table->string('dkim_domain')->nullable();
            $table->string('dkim_selector')->nullable();
            $table->text('dkim_private_key')->nullable();
            $table->string('dmarc_policy')->nullable();
            $table->text('spf_record')->nullable();
            $table->string('bounce_email')->nullable();

            // Extra metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'is_prod']);
            $table->index('health_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
