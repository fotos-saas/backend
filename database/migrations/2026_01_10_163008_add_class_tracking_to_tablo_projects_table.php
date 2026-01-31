<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->integer('expected_class_size')->nullable()->after('status');
            $table->integer('actual_guests_count')->default(0)->after('expected_class_size');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn(['expected_class_size', 'actual_guests_count']);
        });
    }
};
