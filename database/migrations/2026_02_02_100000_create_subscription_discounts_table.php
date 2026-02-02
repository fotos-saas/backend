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
        Schema::create('subscription_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent'); // 1-99
            $table->timestamp('valid_until')->nullable(); // null = forever
            $table->string('note', 500)->nullable();
            $table->string('stripe_coupon_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: egy partnernek csak egy aktív kedvezménye lehet
            $table->unique(['partner_id', 'deleted_at'], 'subscription_discounts_partner_active_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
