<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_archive')->cascadeOnDelete();
            $table->string('alias_name');
            $table->timestamps();

            $table->index('alias_name');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_aliases');
    }
};
