<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_email_snippets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // Megjelenítendő név
            $table->string('slug')->unique();                 // Egyedi azonosító automatizáláshoz
            $table->string('subject')->nullable();            // Előre kitöltött tárgy
            $table->text('content');                          // Szöveg placeholderekkel
            $table->integer('sort_order')->default(0);        // Sorrend a gombokhoz
            $table->boolean('is_active')->default(true);      // Ki/be kapcsolható

            // Automatizálás mezők (későbbi fejlesztéshez)
            $table->boolean('is_auto_enabled')->default(false);
            $table->string('auto_trigger')->nullable();       // 'no_reply_days', 'status_change', 'deadline'
            $table->json('auto_trigger_config')->nullable();  // Trigger konfiguráció (napok, státuszok stb.)
            $table->timestamp('auto_last_run_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_email_snippets');
    }
};
