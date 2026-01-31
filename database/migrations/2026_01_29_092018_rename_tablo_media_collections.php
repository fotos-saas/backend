<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Collection átnevezések:
     * - pending_photos -> tablo_pending
     * - project_photos -> tablo_photos
     * - missing_photos -> tablo_archived
     */
    public function up(): void
    {
        // pending_photos -> tablo_pending
        DB::table('media')
            ->where('collection_name', 'pending_photos')
            ->update(['collection_name' => 'tablo_pending']);

        // project_photos -> tablo_photos
        DB::table('media')
            ->where('collection_name', 'project_photos')
            ->update(['collection_name' => 'tablo_photos']);

        // missing_photos -> tablo_archived
        DB::table('media')
            ->where('collection_name', 'missing_photos')
            ->update(['collection_name' => 'tablo_archived']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // tablo_pending -> pending_photos
        DB::table('media')
            ->where('collection_name', 'tablo_pending')
            ->update(['collection_name' => 'pending_photos']);

        // tablo_photos -> project_photos
        DB::table('media')
            ->where('collection_name', 'tablo_photos')
            ->update(['collection_name' => 'project_photos']);

        // tablo_archived -> missing_photos
        DB::table('media')
            ->where('collection_name', 'tablo_archived')
            ->update(['collection_name' => 'missing_photos']);
    }
};
