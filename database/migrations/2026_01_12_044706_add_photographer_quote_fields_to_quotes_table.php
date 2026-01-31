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
        Schema::table('quotes', function (Blueprint $table) {
            // Kategória: custom (egyedi) vagy photographer (fotós)
            $table->string('quote_category')->default('custom')->after('quote_type');

            // Fotós árlista: [{size, label, price}]
            $table->json('price_list_items')->nullable()->after('content_items');

            // Mennyiségi kedvezmények: [{minQty, maxQty, percentOff, label}]
            $table->json('volume_discounts')->nullable()->after('price_list_items');

            // Index a kategóriához
            $table->index('quote_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['quote_category']);
            $table->dropColumn(['quote_category', 'price_list_items', 'volume_discounts']);
        });
    }
};
