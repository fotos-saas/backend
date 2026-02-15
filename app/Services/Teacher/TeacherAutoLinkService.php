<?php

declare(strict_types=1);

namespace App\Services\Teacher;

use App\Models\TabloPartner;
use App\Models\TeacherArchive;
use App\Models\TeacherChangeLog;
use App\Services\ClaudeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeacherAutoLinkService
{
    public function __construct(
        private ClaudeService $claudeService,
    ) {}

    /**
     * Teljes analízis futtatása: determinisztikus + AI matching.
     *
     * @return array{deterministic: array, ai: array, stats: array}
     */
    public function analyze(int $partnerId, bool $onlyNew = false, ?\Closure $onProgress = null): array
    {
        $query = TeacherArchive::forPartner($partnerId)->with('school:id,name');
        if ($onlyNew) {
            $query->whereNull('linked_group');
        }
        $teachers = $query->get();

        $allTeachers = $onlyNew
            ? TeacherArchive::forPartner($partnerId)->with('school:id,name')->get()
            : $teachers;

        // Összekapcsolt iskolák map: school_id → logikai csoport ID
        $schoolGroupMap = $this->buildSchoolGroupMap($partnerId);

        $onProgress && $onProgress('deterministic', "Determinisztikus matching ({$teachers->count()} tanár)...");

        $deterministicGroups = $this->deterministicMatch($teachers, $allTeachers, $schoolGroupMap);

        // Kik maradtak linkeletlen a determinisztikus fázis után?
        $linkedIds = collect($deterministicGroups)->flatMap(fn ($g) => $g['teacher_ids'])->toArray();
        $unmatched = $teachers->filter(
            fn ($t) => ! in_array($t->id, $linkedIds) && $t->linked_group === null
        );

        $onProgress && $onProgress('ai', "AI matching ({$unmatched->count()} linkeletlen tanár)...");

        $aiGroups = [];
        if ($unmatched->count() > 1) {
            $aiGroups = $this->aiMatch($unmatched, $allTeachers, $onProgress);
        }

        return [
            'deterministic' => $deterministicGroups,
            'ai' => $aiGroups,
            'stats' => [
                'total_teachers' => $teachers->count(),
                'deterministic_groups' => count($deterministicGroups),
                'deterministic_teachers' => count($linkedIds),
                'ai_groups' => count(array_filter($aiGroups, fn ($g) => $g['confidence'] === 'high')),
                'ai_suggested' => count(array_filter($aiGroups, fn ($g) => $g['confidence'] === 'medium')),
                'unmatched' => $unmatched->count() - collect($aiGroups)->flatMap(fn ($g) => $g['teacher_ids'])->count(),
            ],
        ];
    }

    /**
     * Jóváhagyott csoportok végrehajtása: linked_group UPDATE + changelog.
     *
     * @param  array  $groups  [{teacher_ids: [], reason: '', confidence: '', source: ''}]
     */
    public function execute(int $partnerId, array $groups): array
    {
        $created = 0;
        $teachersLinked = 0;
        $suggested = 0;
        $now = now();

        foreach ($groups as $group) {
            $teacherIds = $group['teacher_ids'];
            $confidence = $group['confidence'] ?? 'high';
            $reason = $group['reason'] ?? '';
            $source = $group['source'] ?? 'ai';

            if (count($teacherIds) < 2) {
                continue;
            }

            // Medium confidence → csak changelog (manuális review)
            if ($confidence === 'medium') {
                foreach ($teacherIds as $teacherId) {
                    TeacherChangeLog::create([
                        'teacher_id' => $teacherId,
                        'user_id' => null,
                        'change_type' => 'ai_suggested',
                        'old_value' => null,
                        'new_value' => json_encode($teacherIds),
                        'metadata' => [
                            'reason' => $reason,
                            'confidence' => $confidence,
                            'source' => $source,
                            'suggested_group' => $teacherIds,
                        ],
                        'created_at' => $now,
                    ]);
                }
                $suggested++;

                continue;
            }

            // Nézd meg, van-e már linked_group valamelyik tanárnál → csatlakozz ahhoz
            $existingGroup = TeacherArchive::forPartner($partnerId)
                ->whereIn('id', $teacherIds)
                ->whereNotNull('linked_group')
                ->value('linked_group');

            $groupUuid = $existingGroup ?? Str::uuid()->toString();

            TeacherArchive::forPartner($partnerId)
                ->whereIn('id', $teacherIds)
                ->update(['linked_group' => $groupUuid]);

            foreach ($teacherIds as $teacherId) {
                TeacherChangeLog::create([
                    'teacher_id' => $teacherId,
                    'user_id' => null,
                    'change_type' => 'ai_linked',
                    'old_value' => null,
                    'new_value' => $groupUuid,
                    'metadata' => [
                        'reason' => $reason,
                        'confidence' => $confidence,
                        'source' => $source,
                        'linked_teacher_ids' => $teacherIds,
                    ],
                    'created_at' => $now,
                ]);
            }

            $created++;
            $teachersLinked += count($teacherIds);
        }

        return [
            'groups_created' => $created,
            'teachers_linked' => $teachersLinked,
            'suggestions_saved' => $suggested,
        ];
    }

    // ============================================================
    // Fázis A — Determinisztikus pre-matching
    // ============================================================

    /**
     * @param  array<int, string>  $schoolGroupMap  school_id → logikai csoport ID
     * @return array [{teacher_ids: [], reason: '', confidence: 'high', source: 'deterministic'}]
     */
    private function deterministicMatch(Collection $teachers, Collection $allTeachers, array $schoolGroupMap): array
    {
        $groups = [];

        // Normalizált név → tanárok mapping
        $normalizedMap = [];
        foreach ($allTeachers as $teacher) {
            $normalized = $this->normalizeName($teacher->canonical_name);
            $normalizedMap[$normalized][] = $teacher;
        }

        // Szabály 1: Exact normalized match + különböző iskola (vagy linked school)
        $processedIds = [];
        foreach ($normalizedMap as $normalized => $matchingTeachers) {
            if (count($matchingTeachers) < 2) {
                continue;
            }

            // RAW school_id alapú duplikáció check: ha egyetlen iskolában 2+ ugyanolyan nevű
            // tanár van, az valóban két különböző személy → AI-ra hagyjuk
            $byRawSchool = collect($matchingTeachers)->groupBy('school_id');
            $hasDuplicateInRawSchool = $byRawSchool->contains(fn ($group) => $group->count() > 1);

            if ($hasDuplicateInRawSchool) {
                continue; // AI-ra hagyjuk
            }

            $ids = collect($matchingTeachers)->pluck('id')->toArray();
            $teacherOnlyInScope = collect($matchingTeachers)->filter(
                fn ($t) => $teachers->contains('id', $t->id)
            );

            if ($teacherOnlyInScope->count() < 1 || count($ids) < 2) {
                continue;
            }

            // Ne duplikáljunk már feldolgozott tanárokat
            $newIds = array_diff($ids, $processedIds);
            if (count($newIds) < 2 && count(array_intersect($ids, $processedIds)) > 0) {
                continue;
            }

            $groups[] = [
                'teacher_ids' => array_values($ids),
                'reason' => "Azonos normalizált név: \"{$normalized}\"",
                'confidence' => 'high',
                'source' => 'deterministic',
            ];
            $processedIds = array_merge($processedIds, $ids);
        }

        // Szabály 2: Prefix match (pl. "Ábrahám Hedvig" prefix of "Ábrahám Hedvig Marika")
        $normalizedNames = array_keys($normalizedMap);
        sort($normalizedNames);

        foreach ($normalizedNames as $i => $shorter) {
            if (! empty(array_intersect(
                collect($normalizedMap[$shorter])->pluck('id')->toArray(),
                $processedIds
            ))) {
                continue;
            }

            for ($j = $i + 1; $j < count($normalizedNames); $j++) {
                $longer = $normalizedNames[$j];

                if (! str_starts_with($longer, $shorter.' ')) {
                    continue;
                }

                $shorterTeachers = $normalizedMap[$shorter];
                $longerTeachers = $normalizedMap[$longer];

                // Mindkét oldalon max 1 tanár
                if (count($shorterTeachers) !== 1 || count($longerTeachers) !== 1) {
                    continue;
                }

                // Különböző raw school_id kell (linked school is OK — az is más school_id)
                if ($shorterTeachers[0]->school_id === $longerTeachers[0]->school_id) {
                    continue;
                }

                $ids = [$shorterTeachers[0]->id, $longerTeachers[0]->id];
                if (! empty(array_intersect($ids, $processedIds))) {
                    continue;
                }

                $groups[] = [
                    'teacher_ids' => $ids,
                    'reason' => "Prefix match: \"{$shorter}\" ⊂ \"{$longer}\"",
                    'confidence' => 'high',
                    'source' => 'deterministic',
                ];
                $processedIds = array_merge($processedIds, $ids);
            }
        }

        // Szabály 3: "-né" variánsok (pl. "Kovácsné" ↔ "Kovács")
        foreach ($normalizedMap as $normalized => $matchTeachers) {
            if (! str_contains($normalized, 'né')) {
                continue;
            }

            // "kovácsné" → "kovács", "kovács-né" → "kovács"
            $baseName = preg_replace('/[-\s]?né$/', '', $normalized);
            $baseName = preg_replace('/né\s/', ' ', $baseName);

            if ($baseName === $normalized || ! isset($normalizedMap[$baseName])) {
                continue;
            }

            $neTeachers = $matchTeachers;
            $baseTeachers = $normalizedMap[$baseName];

            if (count($neTeachers) !== 1 || count($baseTeachers) !== 1) {
                continue;
            }

            $ids = [$neTeachers[0]->id, $baseTeachers[0]->id];
            if (! empty(array_intersect($ids, $processedIds))) {
                continue;
            }

            $groups[] = [
                'teacher_ids' => $ids,
                'reason' => "Házassági név variáns: \"{$normalized}\" ↔ \"{$baseName}\"",
                'confidence' => 'high',
                'source' => 'deterministic',
            ];
            $processedIds = array_merge($processedIds, $ids);
        }

        return $groups;
    }

    // ============================================================
    // Fázis B — AI matching (Claude Sonnet batch)
    // ============================================================

    /**
     * @return array [{teacher_ids: [], reason: '', confidence: 'high'|'medium', source: 'ai'}]
     */
    private function aiMatch(Collection $unmatched, Collection $allTeachers, ?\Closure $onProgress = null): array
    {
        $allGroups = [];

        // Iskolánkénti batch-elés
        $bySchool = $unmatched->groupBy('school_id');
        $schoolCount = $bySchool->count();
        $processed = 0;

        // Meglévő linked csoportok kontextusként
        $existingLinkedGroups = $allTeachers->whereNotNull('linked_group')
            ->groupBy('linked_group')
            ->map(fn ($group) => $group->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->canonical_name,
                'school' => $t->school?->name ?? 'Ismeretlen',
            ])->values()->toArray())
            ->toArray();

        // Összekapcsolt iskolák info az AI promptba
        $partnerId = $allTeachers->first()?->partner_id;
        $schoolGroupMap = $partnerId ? $this->buildSchoolGroupMap($partnerId) : [];

        foreach ($bySchool as $schoolId => $schoolTeachers) {
            $processed++;
            $onProgress && $onProgress('ai_batch', "AI batch {$processed}/{$schoolCount} (iskola #{$schoolId})");

            if ($schoolTeachers->count() < 2) {
                $crossSchoolResult = $this->aiCrossSchoolMatch($schoolTeachers, $allTeachers, $existingLinkedGroups, $schoolGroupMap);
                $allGroups = array_merge($allGroups, $crossSchoolResult);

                continue;
            }

            $batchResult = $this->aiBatchForSchool($schoolTeachers, $allTeachers, $existingLinkedGroups, $schoolGroupMap);
            $allGroups = array_merge($allGroups, $batchResult);
        }

        return $allGroups;
    }

    private function aiBatchForSchool(Collection $schoolTeachers, Collection $allTeachers, array $existingLinkedGroups, array $schoolGroupMap): array
    {
        $schoolName = $schoolTeachers->first()?->school?->name ?? 'Ismeretlen iskola';
        $schoolId = $schoolTeachers->first()?->school_id;
        $myLogicalGroup = $schoolGroupMap[$schoolId] ?? "s_{$schoolId}";

        // Az adott iskola tanárai
        $teacherList = $schoolTeachers->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->canonical_name,
            'title' => $t->title_prefix,
            'position' => $t->position,
        ])->values()->toArray();

        // Más LOGIKAI iskolák tanárai (összekapcsolt iskolák = ugyanaz → kizárva)
        $otherSchoolTeachers = $allTeachers
            ->filter(fn ($t) => ($schoolGroupMap[$t->school_id] ?? "s_{$t->school_id}") !== $myLogicalGroup)
            ->whereNull('linked_group')
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->canonical_name,
                'school' => $t->school?->name ?? '?',
                'position' => $t->position,
            ])->values()->toArray();

        // Meglévő linked group-ok összefoglaló
        $linkedGroupContext = [];
        foreach ($existingLinkedGroups as $groupId => $members) {
            $linkedGroupContext[] = [
                'group_id' => $groupId,
                'members' => $members,
            ];
        }

        $systemPrompt = $this->buildAiSystemPrompt();
        $userPrompt = $this->buildAiUserPrompt($schoolName, $teacherList, $otherSchoolTeachers, $linkedGroupContext);

        try {
            $result = $this->claudeService->chatJson(
                $userPrompt,
                $systemPrompt,
                [
                    'model' => 'claude-sonnet-4-5-20250929',
                    'max_tokens' => 4096,
                    'temperature' => 0.0,
                ]
            );

            return $this->parseAiResponse($result);
        } catch (\Exception $e) {
            Log::warning('AI teacher auto-link batch failed', [
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function aiCrossSchoolMatch(Collection $singleTeachers, Collection $allTeachers, array $existingLinkedGroups, array $schoolGroupMap): array
    {
        $teacher = $singleTeachers->first();
        if (! $teacher) {
            return [];
        }

        $normalized = $this->normalizeName($teacher->canonical_name);
        $myLogicalGroup = $schoolGroupMap[$teacher->school_id] ?? "s_{$teacher->school_id}";

        // Keresés hasonló nevű tanárok más LOGIKAI iskolákban
        $candidates = $allTeachers->filter(function ($t) use ($teacher, $normalized, $myLogicalGroup, $schoolGroupMap) {
            if ($t->id === $teacher->id) {
                return false;
            }
            // Összekapcsolt iskolák = ugyanaz a logikai iskola → skip
            $otherGroup = $schoolGroupMap[$t->school_id] ?? "s_{$t->school_id}";
            if ($otherGroup === $myLogicalGroup) {
                return false;
            }
            $otherNorm = $this->normalizeName($t->canonical_name);
            return levenshtein($normalized, $otherNorm) <= 3
                || str_starts_with($otherNorm, $normalized)
                || str_starts_with($normalized, $otherNorm);
        });

        if ($candidates->isEmpty()) {
            return [];
        }

        // AI döntés
        $systemPrompt = $this->buildAiSystemPrompt();
        $userPrompt = "Egyetlen tanár cross-school match vizsgálata:\n\n"
            ."Tanár: {$teacher->canonical_name} (ID: {$teacher->id}, iskola: {$teacher->school?->name}, pozíció: {$teacher->position})\n\n"
            ."Jelöltek más iskolákból:\n"
            .json_encode($candidates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->canonical_name,
                'school' => $t->school?->name,
                'position' => $t->position,
                'linked_group' => $t->linked_group,
            ])->values()->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nHa van match, add meg a groups tömbbe. Ha nincs, üres groups legyen.";

        try {
            $result = $this->claudeService->chatJson(
                $userPrompt,
                $systemPrompt,
                [
                    'model' => 'claude-sonnet-4-5-20250929',
                    'max_tokens' => 2048,
                    'temperature' => 0.0,
                ]
            );

            return $this->parseAiResponse($result);
        } catch (\Exception $e) {
            Log::warning('AI cross-school match failed', [
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function buildAiSystemPrompt(): string
    {
        return <<<'PROMPT'
Magyar tanárnevek összekapcsolási asszisztens vagy. Feladatod: meghatározni, hogy mely tanárok ugyanaz a személy különböző iskolákban/projektekben.

FIGYELJ EZEKRE:
- Titulus eltérés: "Dr. X" vs "X" → UGYANAZ
- Harmadik név hiányzik: "Ábrahám Hedvig Marika" vs "Ábrahám Hedvig" → VALÓSZÍNŰLEG UGYANAZ
- Házassági név: "Kovácsné" vs "Kovács" → LEHETSÉGES (csak ha pozíció is stimmel)
- Kötőjel/szóköz: "Ábrahám-Bura" vs "Ábrahám - Bura" → UGYANAZ
- Ékezet hiba: "Kovacs" vs "Kovács" → UGYANAZ
- Elgépelés: kisebb eltérések → VIZSGÁLD

KRITIKUS SZABÁLYOK:
- Ha egy iskolában KÉT AZONOS NEVŰ tanár van, NE linkeld őket automatikusan! Ez két különböző személy lehet.
- ÖSSZEKAPCSOLT ISKOLÁK: Egyes iskolák össze vannak kapcsolva (pl. "Batthyány Kázmér Gimnázium" és "Szigetszentmiklósi Batthyányi Kázmér Gimnázium" = ugyanaz). Az összekapcsolt iskolákban lévő azonos nevű tanárokat IGENIS linkeld — ők ugyanaz a személy!

A pozíció (tantárgy) segíthet a döntésben: ha két hasonló nevű tanár ugyanazt tanítja, nagyobb a valószínűség.

Válaszolj KIZÁRÓLAG JSON formátumban:
```json
{
  "groups": [
    {
      "confidence": "high",
      "reason": "Rövid indoklás",
      "teacher_ids": [123, 456]
    }
  ],
  "no_match": [789, 101]
}
```

Confidence szintek:
- "high": Biztosan ugyanaz a személy (>90%)
- "medium": Valószínűleg ugyanaz, de manuális ellenőrzés javasolt (60-90%)
- NE adj "low" confidence-t — ha nem biztos, ne linkeld

FONTOS: A teacher_ids-ben MINDIG a megadott ID-ket használd!
PROMPT;
    }

    private function buildAiUserPrompt(string $schoolName, array $teacherList, array $otherSchoolTeachers, array $linkedGroupContext): string
    {
        $prompt = "## Iskola: {$schoolName}\n\n";
        $prompt .= "### Az iskola tanárai (linkelendők):\n";
        $prompt .= json_encode($teacherList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";

        if (! empty($otherSchoolTeachers)) {
            // Limitáljuk a kontextust: max 500 tanár más iskolákból
            $otherLimited = array_slice($otherSchoolTeachers, 0, 500);
            $prompt .= "### Más iskolák tanárai (potenciális cross-school match):\n";
            $prompt .= json_encode($otherLimited, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";
        }

        if (! empty($linkedGroupContext)) {
            $prompt .= "### Meglévő összekapcsolt csoportok (kontextus):\n";
            $prompt .= json_encode(array_slice($linkedGroupContext, 0, 100), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n";
        }

        $prompt .= "Keresd meg az összekapcsolható tanárokat (cross-school match-ek a fenti listákon belül).\n";
        $prompt .= "FIGYELEM: Csak különböző iskolák tanárait linkeld! Egy iskolán belül azonos név = KÉT KÜLÖNBÖZŐ SZEMÉLY!\n";

        return $prompt;
    }

    private function parseAiResponse(array $response): array
    {
        $groups = [];

        foreach ($response['groups'] ?? [] as $group) {
            $confidence = $group['confidence'] ?? 'low';
            $teacherIds = array_map('intval', $group['teacher_ids'] ?? []);
            $reason = $group['reason'] ?? '';

            if (count($teacherIds) < 2) {
                continue;
            }

            if (! in_array($confidence, ['high', 'medium'])) {
                continue;
            }

            $groups[] = [
                'teacher_ids' => $teacherIds,
                'reason' => $reason,
                'confidence' => $confidence,
                'source' => 'ai',
            ];
        }

        return $groups;
    }

    // ============================================================
    // Iskola csoportok
    // ============================================================

    /**
     * Összekapcsolt iskolák map-je: school_id → logikai csoport ID.
     * Ha két iskola össze van kapcsolva (partner_schools.linked_group),
     * ugyanazt a csoport ID-t kapják → az auto-linker egy iskolaként kezeli őket.
     *
     * @return array<int, string> school_id → group identifier
     */
    private function buildSchoolGroupMap(int $partnerId): array
    {
        $rows = DB::table('partner_schools')
            ->where('partner_id', $partnerId)
            ->whereNotNull('linked_group')
            ->select('school_id', 'linked_group')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->school_id] = $row->linked_group;
        }

        return $map;
    }

    // ============================================================
    // Segédfüggvények
    // ============================================================

    public function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Dupla szóközök eltávolítása
        $name = preg_replace('/\s+/', ' ', $name);

        // Titulus eltávolítás
        $name = preg_replace('/^(dr\.?\s*|phd\.?\s*|prof\.?\s*)/i', '', $name);

        // Ékezetek normalizálása (összehasonlításhoz)
        $name = strtr($name, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        ]);

        // Kötőjel/space egységesítés
        $name = preg_replace('/\s*-\s*/', '-', $name);

        return trim($name);
    }
}
