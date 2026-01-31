<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_discussion_id')
                ->constrained('tablo_discussions')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('tablo_discussion_posts')
                ->cascadeOnDelete();
            $table->string('author_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('author_id');
            $table->text('content');
            $table->json('mentions')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->integer('likes_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tablo_discussion_id', 'parent_id', 'created_at']);
            $table->index(['author_type', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_discussion_posts');
    }
};
