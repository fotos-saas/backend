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
        Schema::table('albums', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->after('visibility')
                ->constrained('packages')
                ->nullOnDelete();

            $table->foreignId('price_list_id')
                ->nullable()
                ->after('package_id')
                ->constrained('price_lists')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['price_list_id']);
            $table->dropColumn(['package_id', 'price_list_id']);
        });
    }
};
