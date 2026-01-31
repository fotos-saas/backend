<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_newsfeed_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_newsfeed_post_id')
                ->constrained('tablo_newsfeed_posts')
                ->cascadeOnDelete();
            $table->string('liker_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('liker_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tablo_newsfeed_post_id', 'liker_type', 'liker_id'], 'newsfeed_likes_unique');
            $table->index(['liker_type', 'liker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_newsfeed_likes');
    }
};
