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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['percent', 'amount']);
            $table->decimal('value', 10, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->integer('min_order_value')->nullable();
            $table->json('allowed_emails')->nullable();
            $table->json('allowed_album_ids')->nullable();
            $table->json('allowed_sizes')->nullable();
            $table->integer('max_usage')->nullable();
            $table->integer('usage_count')->default(0);
            $table->boolean('first_order_only')->default(false);
            $table->boolean('auto_apply')->default(false);
            $table->boolean('stackable')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('enabled');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
