<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_persons', function (Blueprint $table) {
            $table->unsignedBigInteger('archive_id')->nullable()->after('media_id');
            $table->unsignedBigInteger('override_photo_id')->nullable()->after('archive_id');

            $table->index('archive_id');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_persons', function (Blueprint $table) {
            $table->dropIndex(['archive_id']);
            $table->dropColumn(['archive_id', 'override_photo_id']);
        });
    }
};
