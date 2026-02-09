<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_tour_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_tour_id')->constrained('help_tours')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('title');
            $table->text('content');
            $table->string('target_selector')->nullable();
            $table->string('placement', 20)->default('bottom');
            $table->string('highlight_type', 20)->default('spotlight');
            $table->boolean('allow_skip')->default(true);
            $table->timestamps();

            $table->unique(['help_tour_id', 'step_number']);
            $table->index('help_tour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_tour_steps');
    }
};
