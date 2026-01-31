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
        // Step 1: Add a temporary column with the new enum values
        Schema::table('email_events', function (Blueprint $table) {
            $table->enum('event_type_new', [
                'user_registered',
                'user_created_credentials',
                'album_created',
                'order_placed',
                'order_status_changed',
                'photo_uploaded',
                'password_reset',
                'manual',
                'tablo_user_registered',
                'tablo_completed',
                'work_session_created',
                'user_magic_login',
                // Legacy/existing values
                'registration_complete',
                'password_changed',
                'order_payment_received',
                'order_shipped',
            ])->nullable()->after('event_type');
        });

        // Step 2: Copy data from old column to new column
        DB::statement('UPDATE email_events SET event_type_new = event_type');

        // Step 3: Drop old column and its index
        Schema::table('email_events', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn('event_type');
        });

        // Step 4: Rename new column to original name
        Schema::table('email_events', function (Blueprint $table) {
            $table->renameColumn('event_type_new', 'event_type');
        });

        // Step 5: Make it not nullable
        DB::statement('ALTER TABLE email_events ALTER COLUMN event_type SET NOT NULL');

        // Step 6: Re-add the index
        Schema::table('email_events', function (Blueprint $table) {
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse: remove user_magic_login from enum
        Schema::table('email_events', function (Blueprint $table) {
            $table->enum('event_type_new', [
                'user_registered',
                'user_created_credentials',
                'album_created',
                'order_placed',
                'order_status_changed',
                'photo_uploaded',
                'password_reset',
                'manual',
                'tablo_user_registered',
                'tablo_completed',
                'work_session_created',
                // Legacy/existing values
                'registration_complete',
                'password_changed',
                'order_payment_received',
                'order_shipped',
            ])->nullable()->after('event_type');
        });

        DB::statement('UPDATE email_events SET event_type_new = event_type');

        Schema::table('email_events', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn('event_type');
        });

        Schema::table('email_events', function (Blueprint $table) {
            $table->renameColumn('event_type_new', 'event_type');
        });

        DB::statement('ALTER TABLE email_events ALTER COLUMN event_type SET NOT NULL');

        Schema::table('email_events', function (Blueprint $table) {
            $table->index('event_type');
        });
    }
};
