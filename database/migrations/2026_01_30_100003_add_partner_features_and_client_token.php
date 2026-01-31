<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add features JSON field to tablo_partners
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->json('features')->nullable()->after('local_id');
        });

        // Add partner_client_id to personal_access_tokens for client auth
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_client_id')->nullable()->after('tokenable_id');
            $table->index('partner_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn('features');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['partner_client_id']);
            $table->dropColumn('partner_client_id');
        });
    }
};
