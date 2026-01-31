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
            $table->text('stripe_secret_key')->nullable()->after('is_active');
            $table->text('stripe_public_key')->nullable()->after('stripe_secret_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_public_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_settings', function (Blueprint $table) {
            $table->dropColumn(['stripe_secret_key', 'stripe_public_key', 'stripe_webhook_secret']);
        });
    }
};
