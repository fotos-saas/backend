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
        Schema::create('tablo_order_analyses', function (Blueprint $table) {
            $table->id();

            // Kapcsolatok
            $table->foreignId('tablo_project_id')
                ->nullable()
                ->constrained('tablo_projects')
                ->nullOnDelete();

            $table->foreignId('project_email_id')
                ->nullable()
                ->constrained('project_emails')
                ->nullOnDelete();

            // PDF fájl
            $table->string('pdf_path')->nullable();
            $table->string('pdf_filename')->nullable();

            // Státusz
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');
            $table->text('error_message')->nullable();

            // AI elemzés eredménye (JSON)
            $table->jsonb('analysis_data')->nullable();

            // Kinyert adatok (gyors hozzáféréshez)
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            $table->string('school_name')->nullable();
            $table->string('class_name')->nullable();
            $table->integer('student_count')->nullable();
            $table->integer('teacher_count')->nullable();

            // Design preferenciák
            $table->string('tablo_size')->nullable();
            $table->string('font_style')->nullable();
            $table->string('color_scheme')->nullable();
            $table->string('background_style')->nullable();
            $table->text('special_notes')->nullable();

            // Címkék (pl. "mesés", "karakteres", "spotify")
            $table->jsonb('tags')->nullable();

            // Validációs figyelmeztetések
            $table->jsonb('warnings')->nullable();

            // Feldolgozás időpontja
            $table->timestamp('analyzed_at')->nullable();

            $table->timestamps();

            // Indexek
            $table->index('status');
            $table->index('analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_order_analyses');
    }
};
