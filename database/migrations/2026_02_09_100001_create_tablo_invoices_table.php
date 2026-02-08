<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablo_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->foreignId('tablo_project_id')->nullable()->constrained('tablo_projects')->nullOnDelete();
            $table->foreignId('tablo_contact_id')->nullable()->constrained('tablo_contacts')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('external_id', 100)->nullable();
            $table->string('invoice_number', 50)->nullable()->unique();
            $table->string('type', 20)->default('invoice');
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('fulfillment_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('currency', 3)->default('HUF');
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(27.00);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_tax_number', 30)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('comment')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tablo_partner_id', 'status', 'issue_date']);
            $table->index(['external_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablo_invoices');
    }
};
