<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_sample_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')->constrained('tablo_projects')->onDelete('cascade');
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tablo_project_id', 'sort_order']);
        });

        Schema::create('tablo_sample_package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('tablo_sample_packages')->onDelete('cascade');
            $table->integer('version_number');
            $table->text('description');
            $table->timestamps();

            $table->unique(['package_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_sample_package_versions');
        Schema::dropIfExists('tablo_sample_packages');
    }
};
