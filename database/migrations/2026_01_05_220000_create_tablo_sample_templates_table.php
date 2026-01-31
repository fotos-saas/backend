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
        // Template categories (Mesés, háttér, rajzolt, casino, etc.)
        Schema::create('tablo_sample_template_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Sample templates (minta táblók)
        Schema::create('tablo_sample_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path'); // Main image
            $table->string('thumbnail_path')->nullable(); // Optional thumbnail
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Kiemelt minták
            $table->json('tags')->nullable(); // Extra címkék (pl. "új", "népszerű")
            $table->timestamps();
        });

        // Pivot table for many-to-many relationship
        Schema::create('tablo_sample_template_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('tablo_sample_templates')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('tablo_sample_template_categories')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'category_id']);
        });

        // Project template selections (pivot table)
        Schema::create('tablo_project_template_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('tablo_sample_templates')
                ->cascadeOnDelete();
            $table->integer('priority')->default(1); // 1 = first choice, 2 = second, etc.
            $table->timestamps();

            $table->unique(['tablo_project_id', 'template_id']);
        });

        // Add max_template_selections to tablo_projects
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_template_selections')
                ->default(3)
                ->after('has_new_missing_photos')
                ->comment('Maximum number of templates user can select');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn('max_template_selections');
        });

        Schema::dropIfExists('tablo_project_template_selections');
        Schema::dropIfExists('tablo_sample_template_category');
        Schema::dropIfExists('tablo_sample_templates');
        Schema::dropIfExists('tablo_sample_template_categories');
    }
};
