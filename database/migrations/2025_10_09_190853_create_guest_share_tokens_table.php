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
        Schema::create('guest_share_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('expires_at');
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage')->default(999);
            $table->timestamps();

            $table->index('token');
            $table->index('album_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_share_tokens');
    }
};
