<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fotós ↔ Nyomda kapcsolatok pivot tábla.
     * Kétirányú kapcsolat:
     * - Fotós meghívja a nyomdáját
     * - Nyomda toborozza a fotósokat
     */
    public function up(): void
    {
        Schema::create('partner_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_studio_id')->constrained('tablo_partners')->onDelete('cascade');
            $table->foreignId('print_shop_id')->constrained('tablo_partners')->onDelete('cascade');
            $table->string('initiated_by', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['photo_studio_id', 'print_shop_id']);
            $table->index(['photo_studio_id', 'status']);
            $table->index(['print_shop_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_connections');
    }
};
