<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_newsfeed_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_newsfeed_post_id')
                ->constrained('tablo_newsfeed_posts')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_image')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tablo_newsfeed_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_newsfeed_media');
    }
};
