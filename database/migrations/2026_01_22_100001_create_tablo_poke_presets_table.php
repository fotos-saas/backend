<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_poke_presets', function (Blueprint $table) {
            $table->id();

            // Egyedi kulcs az azonosításhoz
            $table->string('key')->unique();

            // Emoji
            $table->string('emoji', 10);

            // Magyar szöveg
            $table->string('text_hu', 200);

            // Melyik kategóriához tartozik (null = mindegyikhez)
            $table->enum('category', ['voting', 'photoshoot', 'image_selection', 'general'])
                ->nullable();

            // Rendezési sorrend
            $table->integer('sort_order')->default(0);

            // Aktív-e
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index a gyors lekérdezéshez
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_poke_presets');
    }
};
