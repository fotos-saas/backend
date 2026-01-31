<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_newsfeed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();
            $table->string('author_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('author_id');
            $table->string('post_type'); // 'announcement' or 'event'
            $table->string('title');
            $table->text('content')->nullable();
            $table->date('event_date')->nullable(); // csak event típusnál
            $table->time('event_time')->nullable();
            $table->string('event_location')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->integer('likes_count')->default(0); // cache
            $table->integer('comments_count')->default(0); // cache
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tablo_project_id', 'is_pinned', 'created_at']);
            $table->index(['author_type', 'author_id']);
            $table->index(['post_type', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_newsfeed_posts');
    }
};
