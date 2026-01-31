<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            // Kapcsolat
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Típus és tartalom
            $table->string('type', 50);
            $table->string('title', 100);
            $table->string('message', 255)->nullable();
            $table->string('emoji', 10)->default('🔔');

            // Állapot
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Action
            $table->string('action_url', 255)->nullable();

            // Metadata (JSONB - PostgreSQL optimalizált JSON típus)
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexek
            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index('created_at');
        });

        // PostgreSQL partial index olvasatlan értesítésekhez (nem támogatott Blueprint-ben!)
        DB::statement('CREATE INDEX idx_app_notifications_user_unread ON app_notifications(user_id, is_read, created_at DESC) WHERE is_read = FALSE');

        // GIN index JSONB metadata-hoz (ha keresel benne)
        DB::statement('CREATE INDEX idx_app_notifications_metadata ON app_notifications USING GIN(metadata)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL indexek törlése
        DB::statement('DROP INDEX IF EXISTS idx_app_notifications_user_unread');
        DB::statement('DROP INDEX IF EXISTS idx_app_notifications_metadata');
        Schema::dropIfExists('app_notifications');
    }
};
