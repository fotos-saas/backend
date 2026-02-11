<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPartner;
use App\Models\TeacherArchive;
use Illuminate\Support\Facades\DB;

class SyncTeacherPhotosAction
{
    /**
     * Tanár fotó szinkronizálás végrehajtása — archív cross-school alapon.
     *
     * Az archívban fotó nélküli tanároknak átmásolja az active_photo_id-t
     * a más iskolánál megtalált donor archív rekordból.
     *
     * @param int[]|null $archiveIds Ha megadva, csak ezeket az archív tanárokat szinkronizálja
     */
    public function execute(int $schoolId, int $partnerId, ?string $classYear = null, ?array $archiveIds = null): array
    {
        // Összekapcsolt iskolák feloldása
        $tabloPartner = TabloPartner::find($partnerId);
        $linkedSchoolIds = $tabloPartner ? $tabloPartner->getLinkedSchoolIds($schoolId) : [$schoolId];

        // Archív tanárok az összekapcsolt iskolákra
        $archives = TeacherArchive::forPartner($partnerId)
            ->active()
            ->whereIn('school_id', $linkedSchoolIds)
            ->whereNull('active_photo_id')
            ->orderBy('canonical_name')
            ->get();

        if ($archives->isEmpty()) {
            return $this->emptyResult(0);
        }

        // Ha archive_ids megadva, csak azokat szinkronizáljuk
        if ($archiveIds !== null) {
            $archives = $archives->whereIn('id', $archiveIds);
        }

        // NÉV alapú deduplikálás (linked iskolák közös tanárai)
        $seenNames = [];
        $uniqueArchives = collect();
        foreach ($archives as $t) {
            $normalizedName = mb_strtolower(trim($t->canonical_name));
            if (isset($seenNames[$normalizedName])) {
                continue;
            }
            $seenNames[$normalizedName] = true;
            $uniqueArchives->push($t);
        }

        if ($uniqueArchives->isEmpty()) {
            return $this->emptyResult(0);
        }

        $synced = 0;
        $noPhoto = 0;
        $details = [];

        DB::transaction(function () use ($uniqueArchives, $partnerId, &$synced, &$noPhoto, &$details) {
            foreach ($uniqueArchives as $t) {
                // Donor keresése: ugyanaz a canonical_name, bármely MÁS archív rekord ami fotóval rendelkezik
                $donor = TeacherArchive::forPartner($partnerId)
                    ->active()
                    ->where('canonical_name', $t->canonical_name)
                    ->where('id', '!=', $t->id)
                    ->whereNotNull('active_photo_id')
                    ->first();

                if (!$donor) {
                    $noPhoto++;
                    $details[] = [
                        'archiveId' => $t->id,
                        'personName' => $t->full_display_name,
                        'status' => 'no_photo',
                    ];
                    continue;
                }

                // Szinkronizálás: active_photo_id átmásolás
                $t->active_photo_id = $donor->active_photo_id;
                $t->save();

                $synced++;
                $details[] = [
                    'archiveId' => $t->id,
                    'personName' => $t->full_display_name,
                    'status' => 'synced',
                    'sourceSchoolId' => $donor->school_id,
                ];
            }
        });

        return [
            'synced' => $synced,
            'noMatch' => 0,
            'noPhoto' => $noPhoto,
            'skipped' => 0,
            'details' => $details,
        ];
    }

    private function emptyResult(int $skipped): array
    {
        return [
            'synced' => 0,
            'noMatch' => 0,
            'noPhoto' => 0,
            'skipped' => $skipped,
            'details' => [],
        ];
    }
}
