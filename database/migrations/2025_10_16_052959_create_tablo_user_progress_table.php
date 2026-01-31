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
        Schema::create('tablo_user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_work_session_id')->nullable()
                ->constrained('work_sessions')
                ->nullOnDelete();
            $table->string('current_step')->default('claiming');
            $table->json('steps_data')->nullable();
            $table->text('cart_comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_session_id']);
            $table->index('current_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_user_progress');
    }
};
