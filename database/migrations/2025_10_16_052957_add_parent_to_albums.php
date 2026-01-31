<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->foreignId('parent_album_id')->nullable()
                ->after('user_id')
                ->constrained('albums')
                ->nullOnDelete();

            $table->index('parent_album_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropForeign(['parent_album_id']);
            $table->dropColumn('parent_album_id');
        });
    }
};
