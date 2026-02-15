<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_archive', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->after('active_photo_id');

            $table->unique(['partner_id', 'external_id'], 'teacher_archive_partner_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_archive', function (Blueprint $table) {
            $table->dropUnique('teacher_archive_partner_external_unique');
            $table->dropColumn('external_id');
        });
    }
};
