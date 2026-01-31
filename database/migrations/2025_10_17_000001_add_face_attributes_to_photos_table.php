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
        // Check which columns exist
        $columnsToAdd = [];
        $existingColumns = Schema::getColumnListing('photos');

        if (! in_array('gender', $existingColumns)) {
            $columnsToAdd[] = 'gender';
        }
        if (! in_array('face_direction', $existingColumns)) {
            $columnsToAdd[] = 'face_direction';
        }
        if (! in_array('face_detected', $existingColumns)) {
            $columnsToAdd[] = 'face_detected';
        }
        if (! in_array('age', $existingColumns)) {
            $columnsToAdd[] = 'age';
        }

        // Add only missing columns
        if (! empty($columnsToAdd)) {
            Schema::table('photos', function (Blueprint $table) use ($columnsToAdd) {
                foreach ($columnsToAdd as $column) {
                    match ($column) {
                        'gender' => $table->string('gender', 20)->nullable()->after('claimed_by'),
                        'face_direction' => $table->string('face_direction', 20)->nullable()->after('claimed_by'),
                        'face_detected' => $table->boolean('face_detected')->nullable()->after('claimed_by'),
                        'age' => $table->integer('age')->nullable()->after('claimed_by'),
                        default => null,
                    };
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $columns = ['gender', 'face_direction', 'face_detected', 'age'];
            $existingColumns = array_filter($columns, fn ($col) => Schema::hasColumn('photos', $col));
            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
