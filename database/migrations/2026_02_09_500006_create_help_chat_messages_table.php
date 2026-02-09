<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_chat_conversation_id')->constrained('help_chat_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index('help_chat_conversation_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_chat_messages');
    }
};
