<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_archive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('tablo_schools')->cascadeOnDelete();
            $table->string('canonical_name');
            $table->string('class_name', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('active_photo_id')->nullable();
            $table->timestamps();

            $table->index('partner_id');
            $table->index('school_id');
            $table->index('canonical_name');
            $table->index(['partner_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_archive');
    }
};
