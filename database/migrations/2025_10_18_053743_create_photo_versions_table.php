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
        Schema::create('photo_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('replaced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_restored')->default(false);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();

            $table->index('photo_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_versions');
    }
};
