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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('guest_address');
            $table->string('tax_number')->nullable()->after('company_name');
            $table->text('billing_address')->nullable()->after('tax_number');
            $table->boolean('is_company_purchase')->default(false)->after('billing_address');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_no');

            $table->index('is_company_purchase');
            $table->index('invoice_issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'tax_number',
                'billing_address',
                'is_company_purchase',
                'invoice_issued_at',
            ]);
        });
    }
};
