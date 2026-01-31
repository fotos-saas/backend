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
        // Drop the constraint and recreate with new values
        // PostgreSQL doesn't allow adding enum values easily, so we need to alter the type
        
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_check");
        
        DB::statement("ALTER TABLE email_events ALTER COLUMN event_type TYPE VARCHAR(255)");
        
        // Optionally add a new check constraint with all values
        $allowedTypes = [
            'user_registered',
            'user_created_credentials',
            'registration_complete',
            'password_changed',
            'album_created',
            'order_placed',
            'order_status_changed',
            'order_payment_received',
            'order_shipped',
            'photo_uploaded',
            'password_reset',
            'work_session_created',
            'manual',
        ];
        
        $typesString = "'".implode("', '", $allowedTypes)."'";
        DB::statement("ALTER TABLE email_events ADD CONSTRAINT email_events_event_type_check CHECK (event_type IN ($typesString))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_check");
        
        $originalTypes = [
            'user_registered',
            'album_created',
            'order_placed',
            'order_status_changed',
            'photo_uploaded',
            'password_reset',
            'manual',
        ];
        
        $typesString = "'".implode("', '", $originalTypes)."'";
        DB::statement("ALTER TABLE email_events ADD CONSTRAINT email_events_event_type_check CHECK (event_type IN ($typesString))");
    }
};

