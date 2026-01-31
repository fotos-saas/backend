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
        // Add order_number to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 20)->unique()->nullable()->after('id');

            $table->index('order_number');
        });

        // Add bank transfer details to payment_methods table
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('bank_account_number')->nullable()->after('icon');
            $table->string('account_holder_name')->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('account_holder_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_number']);
            $table->dropColumn('order_number');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'bank_account_number',
                'account_holder_name',
                'bank_name',
            ]);
        });
    }
};
