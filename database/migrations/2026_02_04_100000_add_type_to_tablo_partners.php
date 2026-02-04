<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Partner típusok:
     * - photo_studio: Fotós partner (előfizetéses modell)
     * - print_shop: Nyomda partner (forgalom alapú díjazás)
     */
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->string('type', 20)->default('photo_studio')->after('local_id');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('type');

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'commission_rate']);
        });
    }
};
