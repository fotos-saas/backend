<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teacher_archive')->cascadeOnDelete();
            $table->string('alias_name', 255);
            $table->timestamps();

            $table->index('teacher_id');
        });

        // GIN trigram index a fuzzy kereséshez
        DB::statement('CREATE INDEX teacher_aliases_alias_name_trgm ON teacher_aliases USING GIN (alias_name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_aliases');
    }
};
