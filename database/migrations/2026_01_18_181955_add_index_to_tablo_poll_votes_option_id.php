<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add index to tablo_poll_option_id for better query performance.
     */
    public function up(): void
    {
        Schema::table('tablo_poll_votes', function (Blueprint $table) {
            $table->index('tablo_poll_option_id', 'tablo_poll_votes_option_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_poll_votes', function (Blueprint $table) {
            $table->dropIndex('tablo_poll_votes_option_id_index');
        });
    }
};
