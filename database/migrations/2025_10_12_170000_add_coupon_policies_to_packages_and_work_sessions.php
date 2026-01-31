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
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('coupon_policy', ['all', 'none', 'specific'])
                ->default('all')
                ->after('selectable_photos_count');
            $table->json('allowed_coupon_ids')
                ->nullable()
                ->after('coupon_policy');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->enum('coupon_policy', ['all', 'none', 'specific'])
                ->default('all')
                ->after('status');
            $table->json('allowed_coupon_ids')
                ->nullable()
                ->after('coupon_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['coupon_policy', 'allowed_coupon_ids']);
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn(['coupon_policy', 'allowed_coupon_ids']);
        });
    }
};
