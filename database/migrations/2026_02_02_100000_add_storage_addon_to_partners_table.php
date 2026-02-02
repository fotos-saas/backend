<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Storage Addon - Extra tárhely vásárlás
     * - additional_storage_gb: Partner által vásárolt extra tárhely GB-ban
     * - stripe_storage_addon_item_id: Stripe SubscriptionItem ID a storage addonhoz
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->integer('additional_storage_gb')->default(0)->after('storage_limit_gb');
            $table->string('stripe_storage_addon_item_id')->nullable()->after('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['additional_storage_gb', 'stripe_storage_addon_item_id']);
        });
    }
};
