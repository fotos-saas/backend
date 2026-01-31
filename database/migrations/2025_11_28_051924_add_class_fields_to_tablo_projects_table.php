<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->string('class_name')->nullable()->after('name');
            $table->string('class_year')->nullable()->after('class_name');
        });

        // Migrate data from JSON to new columns (only if 'data' column exists)
        if (Schema::hasColumn('tablo_projects', 'data')) {
            DB::table('tablo_projects')->whereNotNull('data')->orderBy('id')->each(function ($project) {
                $data = json_decode($project->data, true);
                if ($data) {
                    DB::table('tablo_projects')
                        ->where('id', $project->id)
                        ->update([
                            'class_name' => $data['class_name'] ?? null,
                            'class_year' => $data['class_year'] ?? null,
                        ]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tablo_projects', function (Blueprint $table) {
            $table->dropColumn(['class_name', 'class_year']);
        });
    }
};
