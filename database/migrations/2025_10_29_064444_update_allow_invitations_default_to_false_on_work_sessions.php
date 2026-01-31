<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all existing work sessions to have allow_invitations = false
        DB::table('work_sessions')->update(['allow_invitations' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to true
        DB::table('work_sessions')->update(['allow_invitations' => true]);
    }
};
