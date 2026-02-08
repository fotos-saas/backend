<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_archive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('tablo_partners')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('tablo_schools')->cascadeOnDelete();
            $table->string('canonical_name', 255);
            $table->string('title_prefix', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('active_photo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'school_id']);
            $table->index('canonical_name');
        });

        // GIN trigram index a fuzzy kereséshez
        DB::statement('CREATE INDEX teacher_archive_canonical_name_trgm ON teacher_archive USING GIN (canonical_name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_archive');
    }
};
