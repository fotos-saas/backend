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
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->enum('event_type', [
                'user_registered',
                'album_created',
                'order_placed',
                'order_status_changed',
                'photo_uploaded',
                'password_reset',
                'manual',
            ]);
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->enum('recipient_type', ['user', 'album_users', 'order_user', 'custom']);
            $table->json('custom_recipients')->nullable();
            $table->json('conditions')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('event_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
