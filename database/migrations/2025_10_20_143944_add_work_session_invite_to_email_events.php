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
        // Drop the old constraint
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_check");
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_new_check");
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_new_check1");

        // Add new check constraint with work_session_invite and user_magic_login
        $allowedTypes = [
            'user_registered',
            'user_created_credentials',
            'registration_complete',
            'password_changed',
            'user_magic_login',
            'album_created',
            'order_placed',
            'order_status_changed',
            'order_payment_received',
            'order_shipped',
            'photo_uploaded',
            'password_reset',
            'work_session_created',
            'work_session_invite',
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
        // Revert to previous constraint
        DB::statement("ALTER TABLE email_events DROP CONSTRAINT IF EXISTS email_events_event_type_check");

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
};
