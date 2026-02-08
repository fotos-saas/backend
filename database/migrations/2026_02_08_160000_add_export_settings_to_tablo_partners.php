<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->string('default_zip_content', 50)->default('all');
            $table->string('default_file_naming', 50)->default('original');
            $table->boolean('export_always_ask')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn(['default_zip_content', 'default_file_naming', 'export_always_ask']);
        });
    }
};
