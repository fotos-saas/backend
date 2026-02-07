<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloProject;
use App\Services\NameMatcherService;

/**
 * Fotók AI-alapú párosítása személyekkel.
 *
 * Pending képek IPTC title-jeit hasonlítja össze a párosítatlan személyek neveivel.
 */
class MatchPhotosAction
{
    public function __construct(
        private NameMatcherService $matcherService
    ) {}

    /**
     * Fotók párosítása a projekt személyeivel.
     *
     * @param  int[]|null  $photoIds  Szűrés megadott média ID-kra (opcionális)
     * @return array{success: bool, matches?: array, uncertain?: array, unmatchedNames?: array, unmatchedFiles?: array, summary?: string, message?: string, status?: int}
     */
    public function execute(TabloProject $project, ?array $photoIds = null): array
    {
        // Pending képek lekérdezése
        $photos = $project->getMedia('tablo_pending');

        // Ha van szűrés média ID-kra
        if ($photoIds !== null && count($photoIds) > 0) {
            $photoIds = array_map('intval', $photoIds);
            $photos = $photos->filter(fn ($m) => in_array($m->id, $photoIds, true));
        }

        if ($photos->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Nincsenek feltöltött képek a párosításhoz.',
                'status' => 400,
            ];
        }

        // Még párosítatlan személyek
        $persons = $project->persons()
            ->whereNull('media_id')
            ->orderBy('position')
            ->get();

        if ($persons->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Nincs párosítatlan személy a listában.',
                'status' => 400,
            ];
        }

        // Fájlok összeállítása a matcher-hez
        $files = $photos->map(fn ($m) => [
            'filename' => $m->file_name,
            'title' => $m->getCustomProperty('iptc_title'),
            'mediaId' => $m->id,
        ])->values()->toArray();

        $names = $persons->pluck('name')->toArray();

        // AI párosítás
        $result = $this->matcherService->match($names, $files);

        return [
            'success' => true,
            'matches' => $result->matches,
            'uncertain' => $result->uncertain,
            'unmatchedNames' => $result->unmatchedNames,
            'unmatchedFiles' => $result->unmatchedFiles,
            'summary' => $result->getSummary(),
        ];
    }
}
