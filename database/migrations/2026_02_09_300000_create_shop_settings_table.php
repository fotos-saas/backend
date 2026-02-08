<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')->unique()->constrained('tablo_partners')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->text('welcome_message')->nullable();
            $table->integer('min_order_amount_huf')->default(0);
            $table->integer('shipping_cost_huf')->default(0);
            $table->integer('shipping_free_threshold_huf')->nullable();
            $table->boolean('allow_pickup')->default(true);
            $table->boolean('allow_shipping')->default(false);
            $table->text('terms_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
