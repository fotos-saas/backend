<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->text('payment_stripe_public_key')->nullable()->after('billing_enabled');
            $table->text('payment_stripe_secret_key')->nullable()->after('payment_stripe_public_key');
            $table->text('payment_stripe_webhook_secret')->nullable()->after('payment_stripe_secret_key');
            $table->boolean('payment_stripe_enabled')->default(false)->after('payment_stripe_webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn([
                'payment_stripe_public_key',
                'payment_stripe_secret_key',
                'payment_stripe_webhook_secret',
                'payment_stripe_enabled',
            ]);
        });
    }
};
