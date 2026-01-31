<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Populate original_filename from media table custom_properties
        // PostgreSQL JSON operator: ->> extracts JSON field as text

        // Note: In PostgreSQL, model_type is stored as 'App\Models\Photo' (single backslash)
        // But in the query string, we need to escape it as 'App\\Models\\Photo'
        DB::statement("
            UPDATE photos
            SET original_filename = media.custom_properties->>'original_filename'
            FROM media
            WHERE media.model_type = 'App\\Models\\Photo'
                AND media.model_id = photos.id
                AND media.collection_name = 'photo'
                AND media.custom_properties->>'original_filename' IS NOT NULL
        ");

        // Fallback: Set original_filename to photo ID for photos without media or custom property
        DB::statement("
            UPDATE photos
            SET original_filename = CAST(id AS TEXT)
            WHERE original_filename IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all original_filename values back to null
        DB::table('photos')->update(['original_filename' => null]);
    }
};
