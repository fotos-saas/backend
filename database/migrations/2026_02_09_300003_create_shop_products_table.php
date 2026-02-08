<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->foreignId('shop_paper_size_id')->constrained('shop_paper_sizes')->cascadeOnDelete();
            $table->foreignId('shop_paper_type_id')->constrained('shop_paper_types')->cascadeOnDelete();
            $table->integer('price_huf')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tablo_partner_id', 'shop_paper_size_id', 'shop_paper_type_id'], 'shop_products_partner_size_type_unique');
            $table->index(['tablo_partner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
