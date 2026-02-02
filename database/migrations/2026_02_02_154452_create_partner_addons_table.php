<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Partner addonok táblája - vásárolható kiegészítők (pl. Közösségi csomag)
     */
    public function up(): void
    {
        Schema::create('partner_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('addon_key');  // 'community_pack'
            $table->string('stripe_subscription_item_id')->nullable();
            $table->enum('status', ['active', 'canceled'])->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            // Egy partner csak egyszer vásárolhatja meg ugyanazt az addont
            $table->unique(['partner_id', 'addon_key']);

            // Indexes
            $table->index('addon_key');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_addons');
    }
};
