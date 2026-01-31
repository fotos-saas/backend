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
        Schema::create('invoicing_providers', function (Blueprint $table) {
            $table->id();
            $table->enum('provider_type', ['szamlazz_hu', 'billingo']);
            $table->boolean('is_active')->default(false);
            $table->text('api_key')->nullable();
            $table->text('agent_key')->nullable();
            $table->text('api_v3_key')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('provider_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing_providers');
    }
};
