<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_billing_charges', function (Blueprint $table) {
            $table->foreignId('partner_service_id')
                ->nullable()
                ->after('tablo_person_id')
                ->constrained('partner_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guest_billing_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_service_id');
        });
    }
};
