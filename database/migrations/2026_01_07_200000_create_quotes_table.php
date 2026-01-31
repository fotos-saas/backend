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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            // Alapadatok
            $table->string('customer_name');
            $table->string('customer_title')->nullable(); // Megszólítás (pl. Tisztelt Kovács Úr!)
            $table->date('quote_date');
            $table->string('quote_number')->unique(); // AJ-2026-001
            $table->enum('quote_type', ['repro', 'full_production', 'digital'])->default('full_production');
            $table->string('size')->nullable(); // 43x65, 50x70, Repro

            // Tartalom
            $table->text('intro_text')->nullable(); // Bevezető szöveg placeholderekkel
            $table->json('content_items')->nullable(); // [{title: '', description: ''}]

            // Beállítások
            $table->boolean('is_full_execution')->default(false); // Teljes kivitelezés checkbox
            $table->boolean('has_small_tablo')->default(false); // Van kistabló checkbox
            $table->boolean('has_shipping')->default(false); // Van szállítás checkbox

            // Árazás
            $table->integer('base_price')->default(0); // Forintban
            $table->integer('discount_price')->default(0); // Kedvezményes ár
            $table->integer('small_tablo_price')->default(0); // Kistabló ár
            $table->integer('shipping_price')->default(0); // Szállítás ár

            // Extra szövegek
            $table->text('small_tablo_text')->nullable(); // Kistabló szöveg
            $table->text('discount_text')->nullable(); // Kedvezmény magyarázat
            $table->text('notes')->nullable(); // Egyéb megjegyzések

            $table->timestamps();
            $table->softDeletes();

            // Indexek
            $table->index('quote_date');
            $table->index('quote_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
