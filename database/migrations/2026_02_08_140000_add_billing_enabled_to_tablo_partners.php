<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->boolean('billing_enabled')->default(false)->after('default_free_edit_window_hours');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn('billing_enabled');
        });
    }
};
