<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrálja a meglévő albums.class_id értékeket az album_school_class pivot táblába.
     */
    public function up(): void
    {
        // Meglévő class_id adatok átmásolása a pivot táblába
        DB::table('albums')
            ->whereNotNull('class_id')
            ->get()
            ->each(function ($album) {
                DB::table('album_school_class')->insert([
                    'album_id' => $album->id,
                    'school_class_id' => $album->class_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     *
     * Visszatölti az első class_id-t az albums táblába a pivot táblából,
     * majd törli a pivot tábla bejegyzéseket.
     */
    public function down(): void
    {
        // Visszamásoljuk az első osztályt az albums táblába (csak az első, többit elveszítjük!)
        $pivotRecords = DB::table('album_school_class')
            ->select('album_id', DB::raw('MIN(school_class_id) as school_class_id'))
            ->groupBy('album_id')
            ->get();

        foreach ($pivotRecords as $record) {
            DB::table('albums')
                ->where('id', $record->album_id)
                ->update(['class_id' => $record->school_class_id]);
        }

        // Pivot tábla bejegyzések törlése
        DB::table('album_school_class')->truncate();
    }
};
