<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_schools', function (Blueprint $table) {
            $table->uuid('linked_group')->nullable()->after('school_id');
            $table->index(['partner_id', 'linked_group']);
        });
    }

    public function down(): void
    {
        Schema::table('partner_schools', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'linked_group']);
            $table->dropColumn('linked_group');
        });
    }
};
