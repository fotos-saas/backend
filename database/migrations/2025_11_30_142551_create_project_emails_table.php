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
        Schema::create('project_emails', function (Blueprint $table) {
            $table->id();

            // Project kapcsolat (nullable - lehet hogy nincs hozzárendelve)
            $table->foreignId('tablo_project_id')
                ->nullable()
                ->constrained('tablo_projects')
                ->nullOnDelete();

            // Email azonosító (IMAP message-id, unique)
            $table->string('message_id')->unique();

            // Thread tracking - az eredeti email message_id-ja
            $table->string('thread_id')->nullable()->index();
            $table->string('in_reply_to')->nullable();

            // Feladó és címzett
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->json('cc')->nullable();

            // Tartalom
            $table->string('subject');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Irány és státusz
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->boolean('is_read')->default(false);
            $table->boolean('needs_reply')->default(false);
            $table->boolean('is_replied')->default(false);

            // Csatolmányok (JSON tömb: [{name, size, mime_type, path}])
            $table->json('attachments')->nullable();

            // IMAP adatok
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('imap_folder')->nullable();

            // Email dátuma (amikor küldték, nem amikor szinkronizáltuk)
            $table->timestamp('email_date')->nullable();

            $table->timestamps();

            // Indexek a gyors kereséshez
            $table->index(['from_email', 'email_date']);
            $table->index(['to_email', 'email_date']);
            $table->index(['direction', 'needs_reply']);
            $table->index('email_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_emails');
    }
};
