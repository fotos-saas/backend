<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')
                ->constrained('tablo_partners')
                ->cascadeOnDelete();
            $table->foreignId('partner_client_id')
                ->constrained('partner_clients')
                ->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['tablo', 'selection'])->default('selection');
            $table->enum('status', ['draft', 'claiming', 'retouch', 'tablo', 'completed'])->default('draft');
            $table->unsignedInteger('max_selections')->nullable();
            $table->unsignedInteger('min_selections')->nullable();
            $table->unsignedInteger('max_retouch_photos')->nullable()->default(5);
            $table->json('settings')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index('tablo_partner_id');
            $table->index('partner_client_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_albums');
    }
};
