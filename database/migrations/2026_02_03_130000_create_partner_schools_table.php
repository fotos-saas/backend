<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates partner_schools pivot table to link partners with schools
     * without requiring a project.
     */
    public function up(): void
    {
        Schema::create('partner_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('tablo_schools')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: each partner can link to a school only once
            $table->unique(['partner_id', 'school_id']);
        });

        // Migrate existing data: create partner_schools entries from existing projects
        DB::statement("
            INSERT INTO partner_schools (partner_id, school_id, created_at, updated_at)
            SELECT DISTINCT partner_id, school_id, NOW(), NOW()
            FROM tablo_projects
            WHERE school_id IS NOT NULL
            ON CONFLICT (partner_id, school_id) DO NOTHING
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_schools');
    }
};
