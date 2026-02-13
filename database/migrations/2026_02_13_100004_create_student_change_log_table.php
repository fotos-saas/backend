<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_archive')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('student_id');
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_change_log');
    }
};
