<?php

declare(strict_types=1);

namespace App\Services\Teacher;

use App\Models\TeacherArchive;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherMatchingService
{
    public function __construct(
        private ClaudeService $claudeService,
    ) {}

    /**
     * Tanárnevek párosítása az archívummal.
     *
     * @param  string[]  $names  Bemeneti tanárnevek
     * @param  int  $partnerId  Partner ID
     * @param  int|array  $schoolIds  Iskola ID vagy összekapcsolt iskola ID-k tömbje
     * @return array Párosítási eredmények
     */
    public function matchNames(array $names, int $partnerId, int|array $schoolIds): array
    {
        // Backward compat: int → array
        $schoolIds = is_array($schoolIds) ? $schoolIds : [$schoolIds];

        $results = [];
        $unmatchedForAi = [];

        // Összekapcsolt iskolák összes tanárja (kontextus az AI-nak)
        $teachers = TeacherArchive::forPartner($partnerId)
            ->whereIn('school_id', $schoolIds)
            ->active()
            ->with(['aliases', 'activePhoto'])
            ->get();

        foreach ($names as $inputName) {
            $inputName = trim($inputName);
            if ($inputName === '') continue;

            $normalized = $this->normalizeName($inputName);

            // 1. Exact match
            $exactMatch = $this->findExactMatch($teachers, $normalized);
            if ($exactMatch) {
                $results[] = $this->buildResult($inputName, 'exact', $exactMatch, 1.0);
                continue;
            }

            // 2. pg_trgm fuzzy match
            $fuzzyMatch = $this->findFuzzyMatch($inputName, $partnerId, $schoolIds);
            if ($fuzzyMatch && $fuzzyMatch['similarity'] > 0.6) {
                $teacher = $teachers->firstWhere('id', $fuzzyMatch['id']);
                if ($teacher) {
                    $results[] = $this->buildResult($inputName, 'fuzzy', $teacher, $fuzzyMatch['similarity']);
                    continue;
                }
            }

            // Nem matched - AI-ra megy
            $unmatchedForAi[] = [
                'inputName' => $inputName,
                'fuzzyMatch' => $fuzzyMatch,
            ];
        }

        // 3+4. AI matching a nem matched nevekre
        if (!empty($unmatchedForAi) && $teachers->isNotEmpty()) {
            $aiResults = $this->matchWithAi($unmatchedForAi, $teachers);
            $results = array_merge($results, $aiResults);
        } else {
            // Ha nincs tanár az archívumban, mind no_match
            foreach ($unmatchedForAi as $item) {
                $results[] = [
                    'inputName' => $item['inputName'],
                    'matchType' => 'no_match',
                    'teacherId' => null,
                    'teacherName' => null,
                    'photoUrl' => null,
                    'confidence' => 0,
                ];
            }
        }

        return $results;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Dupla szóközök eltávolítása
        $name = preg_replace('/\s+/', ' ', $name);
        // Title prefix kinyerés (Dr., PhD, Prof.)
        $name = preg_replace('/^(dr\.?\s*|phd\.?\s*|prof\.?\s*)/i', '', $name);
        return trim($name);
    }

    private function findExactMatch($teachers, string $normalizedName): ?TeacherArchive
    {
        foreach ($teachers as $teacher) {
            if (mb_strtolower($teacher->canonical_name) === $normalizedName) {
                return $teacher;
            }
            foreach ($teacher->aliases as $alias) {
                if (mb_strtolower($alias->alias_name) === $normalizedName) {
                    return $teacher;
                }
            }
        }
        return null;
    }

    private function findFuzzyMatch(string $name, int $partnerId, array $schoolIds): ?array
    {
        $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));

        $result = DB::select("
            SELECT ta.id, ta.canonical_name,
                   GREATEST(
                       similarity(ta.canonical_name, ?),
                       COALESCE((SELECT MAX(similarity(al.alias_name, ?)) FROM teacher_aliases al WHERE al.teacher_id = ta.id), 0)
                   ) as similarity
            FROM teacher_archive ta
            WHERE ta.partner_id = ? AND ta.school_id IN ({$placeholders}) AND ta.is_active = true
            HAVING GREATEST(
                       similarity(ta.canonical_name, ?),
                       COALESCE((SELECT MAX(similarity(al.alias_name, ?)) FROM teacher_aliases al WHERE al.teacher_id = ta.id), 0)
                   ) > 0.3
            ORDER BY similarity DESC
            LIMIT 1
        ", array_merge([$name, $name, $partnerId], $schoolIds, [$name, $name]));

        if (empty($result)) {
            return null;
        }

        return [
            'id' => $result[0]->id,
            'canonical_name' => $result[0]->canonical_name,
            'similarity' => (float) $result[0]->similarity,
        ];
    }

    private function matchWithAi(array $unmatchedItems, $teachers): array
    {
        $teacherNames = $teachers->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->canonical_name,
            'title' => $t->title_prefix,
            'aliases' => $t->aliases->pluck('alias_name')->toArray(),
        ])->toArray();

        $inputNames = array_column($unmatchedItems, 'inputName');

        $systemPrompt = <<<'PROMPT'
Magyar tanárnevek párosítási asszisztens vagy. A feladatod a diákok által beírt tanárneveket párosítani a tanár archívumban szereplő nevekkel.

Figyelj ezekre:
- Ékezet hibák (pl. "Kovacs" = "Kovács")
- Becenév / rövidítés (pl. "Kati néni" = "Katona Katalin")
- Névsorrendhiba (pl. "Anna Kovács" = "Kovács Anna")
- Titulus eltérés (pl. "Dr. Nagy" = "Nagy János" ha Dr. Nagy nincs)
- Elgépelés (pl. "Nayg" = "Nagy")

Válaszolj KIZÁRÓLAG JSON formátumban:
```json
{
  "matches": [
    {
      "inputName": "a beírt név",
      "teacherId": 123 vagy null,
      "confidence": 0.0-1.0
    }
  ]
}
```

Ha nem találsz megfelelő párosítást, teacherId legyen null és confidence 0.
PROMPT;

        $userPrompt = "Tanár archívum:\n" . json_encode($teacherNames, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
            "\n\nPárosítandó nevek:\n" . json_encode($inputNames, JSON_UNESCAPED_UNICODE);

        $results = [];

        try {
            // Haiku próba először (gyors + olcsó)
            $aiResponse = $this->claudeService->chatJson(
                $userPrompt,
                $systemPrompt,
                ['model' => 'claude-haiku-4-5-20250929', 'max_tokens' => 2048, 'temperature' => 0.0]
            );

            $matches = $aiResponse['matches'] ?? [];

            // Sonnet fallback az alacsony confidence-ű eredményekre
            $needsSonnet = [];
            foreach ($matches as $match) {
                $inputName = $match['inputName'] ?? '';
                $teacherId = $match['teacherId'] ?? null;
                $confidence = (float) ($match['confidence'] ?? 0);

                if ($teacherId && $confidence >= 0.7) {
                    $teacher = $teachers->firstWhere('id', $teacherId);
                    if ($teacher) {
                        $results[] = $this->buildResult($inputName, 'ai', $teacher, $confidence);
                        continue;
                    }
                }

                if ($teacherId && $confidence >= 0.5 && $confidence < 0.7) {
                    $needsSonnet[] = $inputName;
                    continue;
                }

                $results[] = [
                    'inputName' => $inputName,
                    'matchType' => 'no_match',
                    'teacherId' => null,
                    'teacherName' => null,
                    'photoUrl' => null,
                    'confidence' => 0,
                ];
            }

            // Sonnet fallback
            if (!empty($needsSonnet)) {
                $sonnetResults = $this->matchWithSonnet($needsSonnet, $teacherNames, $teachers, $systemPrompt);
                $results = array_merge($results, $sonnetResults);
            }
        } catch (\Exception $e) {
            Log::warning('Teacher AI matching failed, falling back to fuzzy results', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: use fuzzy results where available
            foreach ($unmatchedItems as $item) {
                $fuzzy = $item['fuzzyMatch'];
                if ($fuzzy) {
                    $teacher = $teachers->firstWhere('id', $fuzzy['id']);
                    if ($teacher) {
                        $results[] = $this->buildResult($item['inputName'], 'fuzzy', $teacher, $fuzzy['similarity']);
                        continue;
                    }
                }
                $results[] = [
                    'inputName' => $item['inputName'],
                    'matchType' => 'no_match',
                    'teacherId' => null,
                    'teacherName' => null,
                    'photoUrl' => null,
                    'confidence' => 0,
                ];
            }
        }

        return $results;
    }

    private function matchWithSonnet(array $names, array $teacherNames, $teachers, string $systemPrompt): array
    {
        $results = [];

        try {
            $userPrompt = "Tanár archívum:\n" . json_encode($teacherNames, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
                "\n\nPárosítandó nevek:\n" . json_encode($names, JSON_UNESCAPED_UNICODE);

            $aiResponse = $this->claudeService->chatJson(
                $userPrompt,
                $systemPrompt,
                ['model' => 'claude-sonnet-4-5-20250929', 'max_tokens' => 2048, 'temperature' => 0.0]
            );

            foreach ($aiResponse['matches'] ?? [] as $match) {
                $inputName = $match['inputName'] ?? '';
                $teacherId = $match['teacherId'] ?? null;
                $confidence = (float) ($match['confidence'] ?? 0);

                if ($teacherId && $confidence >= 0.7) {
                    $teacher = $teachers->firstWhere('id', $teacherId);
                    if ($teacher) {
                        $results[] = $this->buildResult($inputName, 'ai_sonnet', $teacher, $confidence);
                        continue;
                    }
                }

                $results[] = [
                    'inputName' => $inputName,
                    'matchType' => 'no_match',
                    'teacherId' => null,
                    'teacherName' => null,
                    'photoUrl' => null,
                    'confidence' => 0,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Sonnet teacher matching failed', ['error' => $e->getMessage()]);

            foreach ($names as $name) {
                $results[] = [
                    'inputName' => $name,
                    'matchType' => 'no_match',
                    'teacherId' => null,
                    'teacherName' => null,
                    'photoUrl' => null,
                    'confidence' => 0,
                ];
            }
        }

        return $results;
    }

    private function buildResult(string $inputName, string $matchType, TeacherArchive $teacher, float $confidence): array
    {
        return [
            'inputName' => $inputName,
            'matchType' => $matchType,
            'teacherId' => $teacher->id,
            'teacherName' => $teacher->full_display_name,
            'photoUrl' => $teacher->photo_thumb_url,
            'confidence' => round($confidence, 2),
        ];
    }
}
