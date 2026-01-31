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
        Schema::create('guest_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_token_id')->constrained('guest_share_tokens')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->boolean('selected')->default(false);
            $table->integer('quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['guest_token_id', 'photo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_selections');
    }
};
