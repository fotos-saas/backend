<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_billing_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')->constrained('tablo_projects')->cascadeOnDelete();
            $table->foreignId('tablo_guest_session_id')->nullable()->constrained('tablo_guest_sessions')->nullOnDelete();
            $table->foreignId('tablo_person_id')->nullable()->constrained('tablo_persons')->nullOnDelete();

            $table->string('charge_number')->unique();
            $table->string('service_type'); // photo_change, extra_retouch, late_fee, rush_fee, additional_copy, custom
            $table->string('description');
            $table->integer('amount_huf');
            $table->string('status')->default('pending'); // pending, paid, cancelled, refunded

            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Stripe-ready
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();

            // Számlázás-ready
            $table->string('invoice_number')->nullable();
            $table->string('invoice_url')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexek
            $table->index(['tablo_project_id', 'status']);
            $table->index('tablo_guest_session_id');
            $table->index('tablo_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_billing_charges');
    }
};
