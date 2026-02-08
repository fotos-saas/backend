<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teacher_archive')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->smallInteger('year');
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('teacher_id');
        });

        // Partial unique index: egy tanárhoz maximum egy aktív fotó
        DB::statement('CREATE UNIQUE INDEX teacher_photos_active_unique ON teacher_photos (teacher_id) WHERE is_active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_photos');
    }
};
