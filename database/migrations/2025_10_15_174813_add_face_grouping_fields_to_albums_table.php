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
            $table->string('face_grouping_status')->nullable()->after('zip_processed_images');
            $table->integer('face_total_photos')->nullable()->after('face_grouping_status');
            $table->integer('face_processed_photos')->nullable()->default(0)->after('face_total_photos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['face_grouping_status', 'face_total_photos', 'face_processed_photos']);
        });
    }
};
