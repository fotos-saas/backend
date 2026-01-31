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
        Schema::create('tablo_missing_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')->constrained('tablo_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('local_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('tablo_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_missing_persons');
    }
};
