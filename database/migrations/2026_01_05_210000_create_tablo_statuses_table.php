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
        Schema::create('tablo_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color')->default('gray');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add tablo_status_id to tablo_projects
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->foreignId('tablo_status_id')
                ->nullable()
                ->after('status')
                ->constrained('tablo_statuses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tablo_status_id');
        });

        Schema::dropIfExists('tablo_statuses');
    }
};
