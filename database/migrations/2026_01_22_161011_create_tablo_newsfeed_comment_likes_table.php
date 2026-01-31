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
        Schema::create('tablo_newsfeed_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_newsfeed_comment_id')
                ->constrained('tablo_newsfeed_comments')
                ->onDelete('cascade');
            $table->string('liker_type', 20); // 'contact' vagy 'guest'
            $table->unsignedBigInteger('liker_id');
            $table->string('reaction', 10)->default('❤️');
            $table->timestamp('created_at')->useCurrent();

            // Egy felhasználó csak egyszer reagálhat egy kommentre
            $table->unique(['tablo_newsfeed_comment_id', 'liker_type', 'liker_id'], 'newsfeed_comment_like_unique');

            // Indexek a gyors lekérdezéshez
            $table->index(['liker_type', 'liker_id'], 'newsfeed_comment_like_liker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_newsfeed_comment_likes');
    }
};
