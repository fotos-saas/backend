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
        Schema::table('smtp_accounts', function (Blueprint $table) {
            $table->string('imap_host')->nullable()->after('bounce_email');
            $table->integer('imap_port')->default(993)->after('imap_host');
            $table->string('imap_encryption')->default('ssl')->after('imap_port');
            $table->string('imap_username')->nullable()->after('imap_encryption');
            $table->string('imap_password')->nullable()->after('imap_username');
            $table->string('imap_sent_folder')->default('Sent')->after('imap_password');
            $table->boolean('imap_save_sent')->default(false)->after('imap_sent_folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_username',
                'imap_password',
                'imap_sent_folder',
                'imap_save_sent',
            ]);
        });
    }
};
