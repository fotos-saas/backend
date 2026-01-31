<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_badges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Egyedi azonosító (pl. first_post)');
            $table->string('name')->comment('Badge neve (magyarul)');
            $table->text('description')->comment('Rövid leírás');
            $table->enum('tier', ['bronze', 'silver', 'gold'])->comment('Badge szint');
            $table->string('icon')->comment('Heroicon név (pl. heroicon-o-star)');
            $table->string('color')->comment('Tailwind szín (pl. amber-500)');
            $table->integer('points')->default(0)->comment('Pont jutalom a badge megszerzésekor');
            $table->json('criteria')->comment('Kritériumok (pl. {"posts": 1})');
            $table->integer('sort_order')->default(0)->comment('Rendezési sorrend');
            $table->boolean('is_active')->default(true)->comment('Aktív-e a badge');
            $table->timestamps();

            $table->index('tier');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_badges');
    }
};
