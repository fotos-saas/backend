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
        Schema::create('tablo_schools', function (Blueprint $table) {
            $table->id();
            $table->string('local_id')->nullable()->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Add school_id to projects
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('partner_id')->constrained('tablo_schools')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });

        Schema::dropIfExists('tablo_schools');
    }
};
