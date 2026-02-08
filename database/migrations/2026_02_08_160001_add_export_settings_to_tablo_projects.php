<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->string('export_zip_content', 50)->nullable();
            $table->string('export_file_naming', 50)->nullable();
            $table->boolean('export_always_ask')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn(['export_zip_content', 'export_file_naming', 'export_always_ask']);
        });
    }
};
