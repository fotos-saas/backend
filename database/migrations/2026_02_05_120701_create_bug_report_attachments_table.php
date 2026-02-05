<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hibajelentés képmellékletek.
     */
    public function up(): void
    {
        Schema::create('bug_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_report_id')->constrained('bug_reports')->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size_bytes');
            $table->string('storage_path');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_report_attachments');
    }
};
