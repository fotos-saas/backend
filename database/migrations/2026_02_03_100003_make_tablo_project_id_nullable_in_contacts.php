<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make tablo_project_id nullable in tablo_contacts.
     * Now that contacts belong to partners and use a pivot table,
     * project_id is no longer required.
     */
    public function up(): void
    {
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->foreignId('tablo_project_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_contacts', function (Blueprint $table) {
            $table->foreignId('tablo_project_id')->nullable(false)->change();
        });
    }
};
