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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Company info
            $table->string('company_name');
            $table->string('tax_number')->nullable();

            // Billing address
            $table->string('billing_country')->default('Magyarország');
            $table->string('billing_postal_code');
            $table->string('billing_city');
            $table->string('billing_address');
            $table->string('phone')->nullable();

            // Plan & Subscription
            $table->string('plan')->default('alap'); // alap, iskola, studio
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly

            // Stripe
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('subscription_status')->default('pending'); // pending, active, canceled, past_due

            // Subscription dates
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Plan limits (can override defaults)
            $table->integer('storage_limit_gb')->nullable();
            $table->integer('max_classes')->nullable();
            $table->json('features')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('plan');
            $table->index('subscription_status');
            $table->index('stripe_customer_id');
            $table->index('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
