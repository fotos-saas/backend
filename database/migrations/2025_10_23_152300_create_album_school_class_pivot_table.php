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
        Schema::create('album_school_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint: egy album-osztály páros csak egyszer szerepelhet
            $table->unique(['album_id', 'school_class_id'], 'unique_album_class');

            // Indexek performance-hoz
            $table->index('album_id');
            $table->index('school_class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_school_class');
    }
};
