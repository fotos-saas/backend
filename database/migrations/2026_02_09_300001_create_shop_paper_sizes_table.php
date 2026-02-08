<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_paper_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->string('name', 50);
            $table->decimal('width_cm', 6, 2);
            $table->decimal('height_cm', 6, 2);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tablo_partner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_paper_sizes');
    }
};
