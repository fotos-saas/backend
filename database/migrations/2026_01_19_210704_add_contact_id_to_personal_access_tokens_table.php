<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hozzáadja a contact_id mezőt a personal_access_tokens táblához.
     * Ez lehetővé teszi, hogy a 'code' belépéssel bejelentkezett felhasználók
     * kapcsolattartóként (contact) működjenek a newsfeed/forum funkcióknál.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('tablo_project_id');

            // Foreign key a tablo_contacts táblára
            $table->foreign('contact_id')
                ->references('id')
                ->on('tablo_contacts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
