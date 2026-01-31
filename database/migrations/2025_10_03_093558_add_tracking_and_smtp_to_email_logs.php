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
        Schema::table('email_logs', function (Blueprint $table) {
            // SMTP account used
            $table->foreignId('smtp_account_id')->nullable()->after('email_template_id')->constrained()->nullOnDelete();

            // Email priority
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal')->after('smtp_account_id');

            // Tracking
            $table->uuid('tracking_token')->nullable()->unique()->after('priority');
            $table->timestamp('opened_at')->nullable()->after('tracking_token');
            $table->integer('open_count')->default(0)->after('opened_at');
            $table->timestamp('clicked_at')->nullable()->after('open_count');
            $table->integer('click_count')->default(0)->after('clicked_at');

            // Bounce & Unsubscribe
            $table->enum('bounce_type', ['hard', 'soft', 'complaint'])->nullable()->after('click_count');
            $table->timestamp('bounced_at')->nullable()->after('bounce_type');
            $table->timestamp('unsubscribed_at')->nullable()->after('bounced_at');

            // Indexes for tracking queries
            $table->index('tracking_token');
            $table->index('opened_at');
            $table->index('clicked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropForeign(['smtp_account_id']);
            $table->dropIndex(['tracking_token']);
            $table->dropIndex(['opened_at']);
            $table->dropIndex(['clicked_at']);
            $table->dropColumn([
                'smtp_account_id',
                'priority',
                'tracking_token',
                'opened_at',
                'open_count',
                'clicked_at',
                'click_count',
                'bounce_type',
                'bounced_at',
                'unsubscribed_at',
            ]);
        });
    }
};
