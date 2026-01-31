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
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('push_enabled')->default(true);
            // V1: normal/quiet mód, PostgreSQL-ben string + CHECK constraint
            $table->string('mode', 20)->default('normal');
            $table->jsonb('categories')->default('{"votes":true,"pokes":true,"mentions":true,"announcements":true,"replies":true,"events":true,"samples":false,"dailyDigest":false}');

            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable()->default('23:00:00');
            $table->time('quiet_hours_end')->nullable()->default('07:00:00');

            $table->timestamps();
        });

        // PostgreSQL CHECK constraint a mode mezőre
        DB::statement("ALTER TABLE user_notification_settings ADD CONSTRAINT check_mode CHECK (mode IN ('normal', 'quiet'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};
