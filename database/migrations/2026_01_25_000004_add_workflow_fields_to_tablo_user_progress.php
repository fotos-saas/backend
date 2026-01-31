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
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            // Retouch photo selections - JSON array of photo IDs
            $table->json('retouch_photo_ids')
                ->nullable()
                ->after('cart_comment');

            // Selected tablo photo - single photo ID
            $table->foreignId('tablo_photo_id')
                ->nullable()
                ->after('retouch_photo_ids')
                ->constrained('photos')
                ->nullOnDelete();

            // Workflow status enum
            $table->string('workflow_status', 20)
                ->default('in_progress')
                ->after('tablo_photo_id');

            // Finalization timestamp
            $table->timestamp('finalized_at')
                ->nullable()
                ->after('workflow_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_user_progress', function (Blueprint $table) {
            $table->dropForeign(['tablo_photo_id']);
            $table->dropColumn([
                'retouch_photo_ids',
                'tablo_photo_id',
                'workflow_status',
                'finalized_at',
            ]);
        });
    }
};
