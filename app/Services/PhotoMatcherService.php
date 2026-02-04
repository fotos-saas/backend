<?php

namespace App\Services;

use App\DTOs\PhotoMatchResult;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fotó párosító service.
 * AI alapú projekt detektálás és név-fájlnév párosítás.
 */
class PhotoMatcherService
{
    private const PROJECT_DETECTION_SYSTEM_PROMPT = <<<'PROMPT'
Te egy fájlnév-projekt párosító asszisztens vagy magyar iskolai tablófotókhoz.
A fájlnevekből próbáld kitalálni, melyik iskolai projekthez tartozhatnak.

Keress iskola nevére, osztálynévre, évfolyamra utaló jeleket:
- "petofi_12a_kovacs.jpg" → Petőfi iskola, 12.A osztály
- "nagy_gimnazium_tanar_kiss.jpg" → Nagy Gimnázium, tanár
- Ékezetek hiányozhatnak: "petofi" = "Petőfi"

Ha a fájlnévből nem derül ki egyértelműen a projekt, adj null-t.

VÁLASZ SZIGORÚAN JSON FORMÁTUMBAN (semmi más szöveg nem lehet előtte/utána):
{
  "assignments": [
    {"filename": "fajlnev.jpg", "project_id": 123, "confidence": "high"},
    {"filename": "masik.jpg", "project_id": null, "confidence": "none", "reason": "nem beazonosítható"}
  ]
}

CONFIDENCE ÉRTÉKEK:
- "high": Egyértelmű egyezés (iskola + osztály megtalálható)
- "medium": Részleges egyezés (csak iskola vagy osztály)
- "low": Bizonytalan, de lehetséges
- "none": Nem beazonosítható
PROMPT;

    public function __construct(
        protected NameMatcherService $nameMatcher,
        protected ClaudeService $claudeService
    ) {}

    /**
     * Teljes párosítás: projekt detektálás + név-fájlnév egyeztetés.
     *
     * @param  array<int, array{filename: string, path?: string, stream?: resource, mediaId?: int}>  $files
     */
    public function matchPhotos(array $files, string $type, ?int $forceProjectId = null): PhotoMatchResult
    {
        if (empty($files)) {
            return new PhotoMatchResult();
        }

        // Ha van force project, mindent ahhoz rendel
        if ($forceProjectId) {
            $project = TabloProject::find($forceProjectId);
            if ($project) {
                return $this->matchToProject($project, $files, $type);
            }
        }

        // AI alapú projekt detektálás
        $projectAssignments = $this->detectProjects($files);

        $allMatches = [];
        $allUncertain = [];
        $allOrphans = [];

        foreach ($projectAssignments as $projectId => $projectFiles) {
            if ($projectId === 'unknown' || $projectId === null) {
                // Nincs projekt → orphan
                foreach ($projectFiles as $file) {
                    $allOrphans[] = [
                        'filename' => $file['filename'],
                        'media_id' => $file['mediaId'] ?? null,
                        'suggested_name' => $this->extractNameFromFilename($file['filename']),
                        'reason' => 'no_project_detected',
                    ];
                }
                continue;
            }

            $project = TabloProject::find($projectId);
            if (! $project) {
                foreach ($projectFiles as $file) {
                    $allOrphans[] = [
                        'filename' => $file['filename'],
                        'media_id' => $file['mediaId'] ?? null,
                        'reason' => 'project_not_found',
                    ];
                }
                continue;
            }

            // Párosítás az adott projekthez
            $result = $this->matchToProject($project, $projectFiles, $type);

            $allMatches = array_merge($allMatches, $result->matches);
            $allUncertain = array_merge($allUncertain, $result->uncertain);
            $allOrphans = array_merge($allOrphans, $result->orphans);
        }

        return new PhotoMatchResult($allMatches, $allUncertain, $allOrphans);
    }

    /**
     * Egy adott projekthez párosítás.
     *
     * @param  array<int, array{filename: string, mediaId?: int}>  $files
     */
    public function matchToProject(TabloProject $project, array $files, string $type): PhotoMatchResult
    {
        // Hiányzó személyek az adott projektben
        $persons = $project->persons()
            ->whereNull('media_id')
            ->where('type', $type)
            ->get();

        if ($persons->isEmpty()) {
            // Nincs hiányzó személy → mind orphan
            $orphans = array_map(fn ($f) => [
                'filename' => $f['filename'],
                'media_id' => $f['mediaId'] ?? null,
                'suggested_name' => $this->extractNameFromFilename($f['filename']),
                'reason' => 'no_missing_persons_in_project',
            ], $files);

            return new PhotoMatchResult(orphans: $orphans);
        }

        // Név-fájlnév párosítás
        $names = $persons->pluck('name')->toArray();
        $nameMatchResult = $this->nameMatcher->match($names, $files);

        $matches = [];
        $uncertain = [];
        $orphans = [];

        // Sikeres párosítások
        foreach ($nameMatchResult->matches as $match) {
            $person = $persons->first(fn ($p) =>
                mb_strtolower(trim($p->name)) === mb_strtolower(trim($match['name']))
            );

            if ($person && ! empty($match['mediaId'])) {
                $matches[] = [
                    'person_id' => $person->id,
                    'person_name' => $match['name'],
                    'media_id' => $match['mediaId'],
                    'filename' => $match['filename'],
                    'project_id' => $project->id,
                    'project_name' => $project->display_name ?? $project->name,
                    'confidence' => $match['confidence'],
                ];
            }
        }

        // Bizonytalan esetek
        foreach ($nameMatchResult->uncertain as $unc) {
            $uncertain[] = array_merge($unc, [
                'project_id' => $project->id,
            ]);
        }

        // Párosítatlan fájlok → orphan
        foreach ($nameMatchResult->unmatchedFiles as $filename) {
            $file = collect($files)->firstWhere('filename', $filename);
            if ($file) {
                $orphans[] = [
                    'filename' => $filename,
                    'media_id' => $file['mediaId'] ?? null,
                    'suggested_name' => $this->extractNameFromFilename($filename),
                    'reason' => 'no_matching_name',
                ];
            }
        }

        return new PhotoMatchResult($matches, $uncertain, $orphans);
    }

    /**
     * AI alapú projekt detektálás fájlnevekből.
     *
     * @param  array<int, array{filename: string}>  $files
     * @return array<int|string, array>  Projekt ID → fájlok map
     */
    public function detectProjects(array $files): array
    {
        // Összes aktív projekt lekérése
        $projects = TabloProject::with('school')
            ->whereHas('persons', fn ($q) => $q->whereNull('media_id'))
            ->get();

        if ($projects->isEmpty()) {
            return ['unknown' => $files];
        }

        $projectList = $projects->map(fn ($p) => [
            'id' => $p->id,
            'school' => $p->school?->name,
            'class' => $p->class_name,
            'year' => $p->class_year,
        ])->toArray();

        $filenames = array_column($files, 'filename');
        $prompt = $this->buildProjectDetectionPrompt($projectList, $filenames);

        try {
            $result = $this->claudeService->chatJson($prompt, self::PROJECT_DETECTION_SYSTEM_PROMPT, [
                'temperature' => 0.0,
            ]);

            return $this->mapFilesToProjects($result, $files);
        } catch (\Exception $e) {
            Log::error('PhotoMatcher: Project detection failed', [
                'error' => $e->getMessage(),
            ]);

            return ['unknown' => $files];
        }
    }

    /**
     * Projekt detektálás prompt összeállítása.
     */
    protected function buildProjectDetectionPrompt(array $projects, array $filenames): string
    {
        $projectsJson = json_encode($projects, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $filesJson = json_encode($filenames, JSON_UNESCAPED_UNICODE);

        return "PROJEKTEK ({$this->count($projects)} db):\n{$projectsJson}\n\nFÁJLNEVEK ({$this->count($filenames)} db):\n{$filesJson}";
    }

    /**
     * AI eredmény mapping fájlokhoz.
     */
    protected function mapFilesToProjects(array $aiResult, array $files): array
    {
        $grouped = [];
        $assignedFilenames = [];

        foreach ($aiResult['assignments'] ?? [] as $assignment) {
            $projectId = $assignment['project_id'] ?? 'unknown';
            $filename = $assignment['filename'] ?? null;

            if (! $filename) {
                continue;
            }

            $file = collect($files)->firstWhere('filename', $filename);
            if ($file) {
                $grouped[$projectId][] = array_merge($file, [
                    'ai_confidence' => $assignment['confidence'] ?? 'none',
                ]);
                $assignedFilenames[] = $filename;
            }
        }

        // Kimaradt fájlok → unknown
        foreach ($files as $file) {
            if (! in_array($file['filename'], $assignedFilenames)) {
                $grouped['unknown'][] = $file;
            }
        }

        return $grouped;
    }

    /**
     * Név kinyerése fájlnévből (egyszerű heurisztika).
     */
    protected function extractNameFromFilename(string $filename): ?string
    {
        // Kiterjesztés eltávolítása
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Számok eltávolítása a végéről
        $name = preg_replace('/[-_]?\d+$/', '', $name);

        // Alulvonás és kötőjel → szóköz
        $name = str_replace(['_', '-'], ' ', $name);

        // Tisztítás
        $name = trim($name);

        // Üres vagy túl rövid → null
        if (strlen($name) < 3) {
            return null;
        }

        // Nagybetűs szavak
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Tanárok keresése más projektekben (szinkronizáláshoz).
     *
     * @return Collection<int, TabloPerson>
     */
    public function findTeacherInOtherProjects(string $teacherName, int $excludeProjectId): Collection
    {
        return TabloPerson::where('name', $teacherName)
            ->where('type', 'teacher')
            ->where('tablo_project_id', '!=', $excludeProjectId)
            ->whereNull('media_id')
            ->with('project.school')
            ->get();
    }

    /**
     * Elemszám biztonságosan.
     */
    private function count(array $arr): int
    {
        return count($arr);
    }
}
