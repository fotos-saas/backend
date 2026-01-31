<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_discussion_post_id')
                ->constrained('tablo_discussion_posts')
                ->cascadeOnDelete();
            $table->string('liker_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('liker_id');
            $table->timestamps();

            $table->unique(
                ['tablo_discussion_post_id', 'liker_type', 'liker_id'],
                'tablo_post_likes_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_post_likes');
    }
};
