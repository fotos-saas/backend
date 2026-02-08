<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('tablo_partner_id')->constrained('tablo_partners')->cascadeOnDelete();

            // Kliens referencia (egyik kitöltve)
            $table->foreignId('partner_client_id')->nullable()->constrained('partner_clients')->nullOnDelete();
            $table->foreignId('tablo_guest_session_id')->nullable()->constrained('tablo_guest_sessions')->nullOnDelete();

            // Forrás album/galéria
            $table->foreignId('partner_album_id')->nullable()->constrained('partner_albums')->nullOnDelete();
            $table->foreignId('tablo_gallery_id')->nullable()->constrained('tablo_galleries')->nullOnDelete();

            // Kontakt
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            // Összegek
            $table->integer('subtotal_huf')->default(0);
            $table->integer('shipping_cost_huf')->default(0);
            $table->integer('total_huf')->default(0);

            // Státusz
            $table->string('status', 20)->default('pending');

            // Stripe
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Szállítás
            $table->string('delivery_method', 10)->default('pickup');
            $table->text('shipping_address')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('tracking_number')->nullable();

            // Megjegyzés
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            $table->index(['tablo_partner_id', 'status']);
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
