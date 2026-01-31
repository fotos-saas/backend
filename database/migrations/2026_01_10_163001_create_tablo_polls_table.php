<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();
            $table->foreignId('creator_contact_id')
                ->nullable()
                ->constrained('tablo_contacts')
                ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['template', 'custom'])->default('custom');
            $table->boolean('is_free_choice')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_multiple_choice')->default(false);
            $table->integer('max_votes_per_guest')->default(1);
            $table->boolean('show_results_before_vote')->default(false);
            $table->boolean('use_for_finalization')->default(false);
            $table->timestamp('close_at')->nullable();
            $table->timestamps();

            $table->index(['tablo_project_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_polls');
    }
};
