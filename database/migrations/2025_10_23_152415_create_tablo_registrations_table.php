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
        Schema::create('tablo_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_session_id')->constrained('work_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            // Indexek performance-hoz
            $table->index('work_session_id');
            $table->index('user_id');
            $table->index('album_id');
            $table->index('school_class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_registrations');
    }
};
