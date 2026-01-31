<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create pivot table
        Schema::create('album_work_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_session_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['album_id', 'work_session_id']);
            $table->index(['work_session_id', 'album_id']);

            // Unique constraint to prevent duplicate entries
            $table->unique(['album_id', 'work_session_id']);
        });

        // Migrate existing data from albums.work_session_id to pivot table
        DB::statement('
            INSERT INTO album_work_session (album_id, work_session_id, created_at, updated_at)
            SELECT id, work_session_id, created_at, updated_at
            FROM albums
            WHERE work_session_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album_work_session');
    }
};
