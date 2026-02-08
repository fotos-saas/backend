<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_invoice_id')->constrained('tablo_invoices')->cascadeOnDelete();
            $table->foreignId('guest_billing_charge_id')->nullable()->constrained('guest_billing_charges')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('db');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(27.00);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_invoice_items');
    }
};
