<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_chat_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->date('usage_date');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('total_input_tokens')->default(0);
            $table->unsignedInteger('total_output_tokens')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
            $table->index('partner_id');
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_chat_usage');
    }
};
