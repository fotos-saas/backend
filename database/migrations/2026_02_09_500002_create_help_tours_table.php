<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_tours', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('trigger_route');
            $table->jsonb('target_roles')->default('[]');
            $table->jsonb('target_plans')->default('[]');
            $table->string('trigger_type', 30)->default('first_visit');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('trigger_route');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_tours');
    }
};
