<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('content_plain')->nullable();
            $table->string('category', 50);
            $table->jsonb('target_roles')->default('[]');
            $table->jsonb('target_plans')->default('[]');
            $table->jsonb('related_routes')->default('[]');
            $table->jsonb('keywords')->default('[]');
            $table->string('feature_key')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_faq')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('category');
            $table->index('is_published');
            $table->index('is_faq');
            $table->index('feature_key');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
