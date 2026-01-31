<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_polls', function (Blueprint $table) {
            $table->string('cover_image_url')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_polls', function (Blueprint $table) {
            $table->dropColumn('cover_image_url');
        });
    }
};
