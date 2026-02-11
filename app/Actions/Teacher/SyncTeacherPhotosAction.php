<?php

declare(strict_types=1);

namespace App\Actions\Teacher;

use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use App\Services\Teacher\TeacherMatchingService;
use Illuminate\Support\Facades\DB;

class SyncTeacherPhotosAction
{
    public function __construct(
        private readonly TeacherMatchingService $matchingService,
    ) {}

    /**
     * Tanár fotó szinkronizálás végrehajtása — archív fotó hozzárendelés.
     */
    public function execute(int $projectId, int $partnerId): array
    {
        $project = TabloProject::where('id', $projectId)
            ->where('partner_id', $partnerId)
            ->firstOrFail();

        $schoolId = $project->school_id;

        $teachers = TabloPerson::where('tablo_project_id', $projectId)
            ->where('type', 'teacher')
            ->get();

        $total = $teachers->count();

        if ($total === 0 || !$schoolId) {
            return $this->emptyResult($teachers->whereNotNull('media_id')->count());
        }

        $withPhoto = $teachers->whereNotNull('media_id');
        $withoutPhoto = $teachers->whereNull('media_id');
        $skipped = $withPhoto->count();

        if ($withoutPhoto->isEmpty()) {
            return $this->emptyResult($skipped);
        }

        $names = $withoutPhoto->pluck('name')->toArray();
        $matchResults = $this->matchingService->matchNames($names, $partnerId, $schoolId);
        $matchMap = collect($matchResults)->keyBy('inputName');

        $synced = 0;
        $noMatch = 0;
        $noPhoto = 0;
        $details = [];

        DB::transaction(function () use ($withoutPhoto, $matchMap, &$synced, &$noMatch, &$noPhoto, &$details) {
            foreach ($withoutPhoto as $person) {
                $match = $matchMap->get($person->name);

                if (!$match || $match['matchType'] === 'no_match') {
                    $noMatch++;
                    $details[] = [
                        'personId' => $person->id,
                        'personName' => $person->name,
                        'status' => 'no_match',
                    ];
                    continue;
                }

                // Van match — archív tanár active_photo_id lekérése
                $archive = TeacherArchive::find($match['teacherId']);
                if (!$archive || !$archive->active_photo_id) {
                    $noPhoto++;
                    $details[] = [
                        'personId' => $person->id,
                        'personName' => $person->name,
                        'status' => 'no_photo',
                        'matchType' => $match['matchType'],
                        'teacherName' => $match['teacherName'],
                        'confidence' => $match['confidence'],
                    ];
                    continue;
                }

                // Szinkronizálás: media_id átállítás
                $person->media_id = $archive->active_photo_id;
                $person->save();

                $synced++;
                $details[] = [
                    'personId' => $person->id,
                    'personName' => $person->name,
                    'status' => 'synced',
                    'matchType' => $match['matchType'],
                    'teacherName' => $match['teacherName'],
                    'confidence' => $match['confidence'],
                ];
            }
        });

        return [
            'synced' => $synced,
            'noMatch' => $noMatch,
            'noPhoto' => $noPhoto,
            'skipped' => $skipped,
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
