<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teacher_archive')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type', 30);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('teacher_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_change_log');
    }
};
