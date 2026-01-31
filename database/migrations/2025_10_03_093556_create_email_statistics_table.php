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
        Schema::create('email_statistics', function (Blueprint $table) {
            $table->id();

            // Aggregation keys
            $table->foreignId('smtp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->tinyInteger('hour'); // 0-23

            // Statistics counters
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->integer('bounced_count')->default(0);
            $table->integer('unsubscribed_count')->default(0);

            $table->timestamps();

            // Composite index for fast queries
            $table->unique(['smtp_account_id', 'email_template_id', 'date', 'hour'], 'email_stats_unique');
            $table->index(['date', 'hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_statistics');
    }
};
