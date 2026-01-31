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
        Schema::create('navigation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('cascade');
            $table->string('key'); // e.g., "platform-settings", "shipping-payment"
            $table->string('label'); // Display name: "Platform Beállítások"
            $table->integer('sort_order')->default(0); // Group ordering
            $table->boolean('is_system')->default(false); // System groups cannot be deleted
            $table->boolean('collapsed')->default(false); // Default collapse state
            $table->timestamps();

            // One group key per role (NULL = default for all roles)
            $table->unique(['role_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_groups');
    }
};
