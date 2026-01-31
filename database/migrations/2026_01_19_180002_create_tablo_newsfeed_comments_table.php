<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_newsfeed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_newsfeed_post_id')
                ->constrained('tablo_newsfeed_posts')
                ->cascadeOnDelete();
            $table->string('author_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tablo_newsfeed_post_id', 'created_at']);
            $table->index(['author_type', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_newsfeed_comments');
    }
};
