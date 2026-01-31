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
        Schema::create('tablo_projects', function (Blueprint $table) {
            $table->id();
            $table->string('local_id')->nullable()->unique();
            $table->string('external_id')->nullable()->unique();
            $table->string('name');
            $table->foreignId('partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->string('status')->default('not_started');
            $table->boolean('is_aware')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('partner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablo_projects');
    }
};
