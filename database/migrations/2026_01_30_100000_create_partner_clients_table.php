<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tablo_partner_id')
                ->constrained('tablo_partners')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('access_code', 6)->unique()->nullable();
            $table->boolean('access_code_enabled')->default(false);
            $table->timestamp('access_code_expires_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('tablo_partner_id');
            $table->index('access_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_clients');
    }
};
