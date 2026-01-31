<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();
            $table->foreignId('tablo_sample_template_id')
                ->nullable()
                ->constrained('tablo_sample_templates')
                ->nullOnDelete();
            $table->string('creator_type'); // 'contact' or 'guest'
            $table->unsignedBigInteger('creator_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->integer('posts_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->index(['tablo_project_id', 'is_pinned', 'created_at']);
            $table->index(['creator_type', 'creator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_discussions');
    }
};
