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
     * Add partner_id to tablo_contacts and migrate existing data.
     * This enables contacts to belong directly to a partner (like schools).
     */
    public function up(): void
    {
        // Step 1: Add partner_id column as nullable
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('id')
                ->constrained('tablo_partners')
                ->cascadeOnDelete();
        });

        // Step 2: Migrate data - set partner_id from project's partner_id
        DB::statement('
            UPDATE tablo_contacts
            SET partner_id = tablo_projects.partner_id
            FROM tablo_projects
            WHERE tablo_contacts.tablo_project_id = tablo_projects.id
        ');

        // Step 3: Make partner_id NOT NULL (after data migration)
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable(false)->change();
        });

        // Add index for faster lookups
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->index('partner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
