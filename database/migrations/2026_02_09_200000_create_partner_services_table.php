<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('service_type')->default('custom');
            $table->integer('default_price')->default(0);
            $table->string('currency', 3)->default('HUF');
            $table->decimal('vat_percentage', 5, 2)->default(27.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['partner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_services');
    }
};
