<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_brandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name', 100)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_brandings');
    }
};
