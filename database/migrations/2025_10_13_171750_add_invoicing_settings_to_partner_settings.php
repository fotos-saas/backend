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
        Schema::table('partner_settings', function (Blueprint $table) {
            $table->boolean('allow_company_invoicing')->default(false)->after('stripe_webhook_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_settings', function (Blueprint $table) {
            $table->dropColumn('allow_company_invoicing');
        });
    }
};
