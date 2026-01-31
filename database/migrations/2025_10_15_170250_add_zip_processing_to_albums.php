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
            $table->enum('zip_processing_status', ['none', 'pending', 'processing', 'completed', 'failed'])
                ->default('none')
                ->after('visibility');
            $table->integer('zip_total_images')->unsigned()->nullable()->after('zip_processing_status');
            $table->integer('zip_processed_images')->unsigned()->nullable()->after('zip_total_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['zip_processing_status', 'zip_total_images', 'zip_processed_images']);
        });
    }
};
