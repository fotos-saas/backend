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
        Schema::create('conversion_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_job_id')->constrained('conversion_jobs')->onDelete('cascade');
            $table->string('folder_path')->nullable();
            $table->enum('conversion_status', ['pending', 'converting', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_media');
    }
};
