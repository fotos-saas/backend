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
        Schema::create('navigation_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('resource_key'); // e.g., "photos", "albums", "orders"
            $table->string('label')->nullable(); // Custom label override
            $table->string('navigation_group')->nullable(); // Which group it belongs to
            $table->integer('sort_order')->default(0); // Lower = appears first
            $table->boolean('is_visible')->default(true); // Visibility toggle
            $table->timestamps();

            // One resource_key per role
            $table->unique(['role_id', 'resource_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_configurations');
    }
};
