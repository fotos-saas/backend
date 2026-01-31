<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_poll_id')
                ->constrained('tablo_polls')
                ->cascadeOnDelete();
            $table->foreignId('tablo_sample_template_id')
                ->nullable()
                ->constrained('tablo_sample_templates')
                ->nullOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['tablo_poll_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_poll_options');
    }
};
