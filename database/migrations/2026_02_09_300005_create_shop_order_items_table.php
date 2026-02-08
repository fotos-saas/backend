<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->restrictOnDelete();
            $table->unsignedBigInteger('media_id');

            // Snapshot (árváltozás védelem)
            $table->string('paper_size_name', 50);
            $table->string('paper_type_name', 50);
            $table->integer('unit_price_huf');
            $table->integer('quantity')->default(1);
            $table->integer('subtotal_huf');

            $table->timestamps();

            $table->foreign('media_id')->references('id')->on('media')->restrictOnDelete();
            $table->index('shop_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
    }
};
