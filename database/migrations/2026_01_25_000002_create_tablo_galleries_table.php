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
        // Create tablo_galleries table
        Schema::create('tablo_galleries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, archived
            $table->timestamps();
        });

        // Add tablo_gallery_id to tablo_projects
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->foreignId('tablo_gallery_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('tablo_galleries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropForeign(['tablo_gallery_id']);
            $table->dropColumn('tablo_gallery_id');
        });

        Schema::dropIfExists('tablo_galleries');
    }
};
