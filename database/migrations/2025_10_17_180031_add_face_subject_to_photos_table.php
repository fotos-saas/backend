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
        Schema::table('photos', function (Blueprint $table) {
            if (! Schema::hasColumn('photos', 'face_subject')) {
                $table->string('face_subject', 100)->nullable()->after('face_direction')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            if (Schema::hasColumn('photos', 'face_subject')) {
                $table->dropIndex(['face_subject']);
                $table->dropColumn('face_subject');
            }
        });
    }
};
