<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create pivot table for project-contact many-to-many relationship.
     * This allows contacts to be linked to multiple projects.
     */
    public function up(): void
    {
        // Step 1: Create pivot table
        Schema::create('tablo_project_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->constrained('tablo_projects')
                ->cascadeOnDelete();
            $table->foreignId('tablo_contact_id')
                ->constrained('tablo_contacts')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Unique constraint - one contact can only be linked to a project once
            $table->unique(['tablo_project_id', 'tablo_contact_id'], 'project_contact_unique');
        });

        // Step 2: Migrate existing relationships from tablo_contacts
        DB::statement('
            INSERT INTO tablo_project_contacts (tablo_project_id, tablo_contact_id, is_primary, created_at, updated_at)
            SELECT tablo_project_id, id, is_primary, created_at, updated_at
            FROM tablo_contacts
            WHERE tablo_project_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_project_contacts');
    }
};
