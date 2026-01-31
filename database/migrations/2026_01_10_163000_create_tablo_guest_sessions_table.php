<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_project_id')
                ->nullable()
                ->constrained('tablo_projects')
                ->nullOnDelete();
            $table->uuid('session_token')->unique();
            $table->string('device_identifier')->nullable();
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['tablo_project_id', 'session_token']);
            $table->unique(['tablo_project_id', 'guest_email'], 'tablo_guest_sessions_project_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_guest_sessions');
    }
};
