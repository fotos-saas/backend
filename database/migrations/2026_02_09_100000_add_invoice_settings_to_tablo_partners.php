<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->string('invoice_provider', 20)->default('szamlazz_hu');
            $table->boolean('invoice_enabled')->default(false);
            $table->text('invoice_api_key')->nullable();
            $table->string('szamlazz_bank_name', 100)->nullable();
            $table->string('szamlazz_bank_account', 50)->nullable();
            $table->string('szamlazz_reply_email', 100)->nullable();
            $table->string('billingo_block_id', 50)->nullable();
            $table->string('billingo_bank_account_id', 50)->nullable();
            $table->string('invoice_prefix', 20)->default('PS');
            $table->string('invoice_currency', 3)->default('HUF');
            $table->string('invoice_language', 2)->default('hu');
            $table->unsignedSmallInteger('invoice_due_days')->default(8);
            $table->decimal('invoice_vat_percentage', 5, 2)->default(27.00);
            $table->text('invoice_comment')->nullable();
            $table->boolean('invoice_eu_vat')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tablo_partners', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_provider',
                'invoice_enabled',
                'invoice_api_key',
                'szamlazz_bank_name',
                'szamlazz_bank_account',
                'szamlazz_reply_email',
                'billingo_block_id',
                'billingo_bank_account_id',
                'invoice_prefix',
                'invoice_currency',
                'invoice_language',
                'invoice_due_days',
                'invoice_vat_percentage',
                'invoice_comment',
                'invoice_eu_vat',
            ]);
        });
    }
};
