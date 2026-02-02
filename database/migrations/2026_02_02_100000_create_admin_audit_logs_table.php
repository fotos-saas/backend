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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('target_partner_id')->nullable()->constrained('partners')->onDelete('set null');
            $table->string('action'); // 'charge', 'change_plan', 'cancel_subscription', 'view'
            $table->json('details')->nullable(); // Részletek JSON-ben
            $table->string('ip_address', 45)->nullable(); // IPv6 support
            $table->timestamps();

            $table->index(['target_partner_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
