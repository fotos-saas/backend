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
        Schema::create('orphan_photos', function (Blueprint $table) {
            $table->id();
            $table->string('suggested_name')->nullable();
            $table->string('type')->default('unknown');
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('original_filename');
            $table->json('source_info')->nullable();
            $table->json('suggested_projects')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('media_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphan_photos');
    }
};
