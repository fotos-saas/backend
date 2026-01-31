<?php

namespace App\Services;

use App\DTOs\NameMatchResult;
use Illuminate\Support\Facades\Log;

/**
 * Név-fájlnév párosító service Claude AI-val.
 * Magyar tablófotókhoz optimalizált.
 */
class NameMatcherService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Te egy név-fájlnév párosító asszisztens vagy magyar iskolai tablófotókhoz.

FELADAT: Párosítsd össze a személyek neveit a képfájlok neveivel.

SZABÁLYOK:
1. Ékezetek: fájlnevekben általában nincsenek (á→a, é→e, ö/ő→o, ü/ű→u, í→i)
2. Rövidítések: "juhasz g" = "Juhász Gábor" (ha nincs másik G-vel kezdődő Juhász)
3. Hiányzó nevek: "kiss anna.jpg" párosítható "Kiss Anna Mária"-val
4. Elírások: kisebb elírásokat tolerálj ("kataln" ≈ "katalin", "petra" ≈ "péter")
5. Számok a fájlnév végén (01, 08, 12) nem relevánsak, figyelmen kívül hagyandók
6. Ha két vagy több név is illeszkedhetne ugyanarra a fájlra → tedd az uncertain listába
7. Egy fájl csak egy névhez párosítható
8. Egy név csak egy fájlhoz párosítható
9. Ha a fájlnév egyértelműen nem tartozik senkihez → unmatched_files
10. Ha egy névhez nem található fájl → unmatched_names

CONFIDENCE ÉRTÉKEK:
- "high": Pontos vagy majdnem pontos egyezés (pl. "kovacs janos" ↔ "Kovács János")
- "medium": Részleges egyezés (hiányzó név, kis elírás, rövidítés)

VÁLASZ SZIGORÚAN JSON FORMÁTUMBAN (semmi más szöveg nem lehet előtte/utána):
{
  "matches": [
    {"name": "Teljes Név", "filename": "fajlnev.jpg", "confidence": "high|medium"}
  ],
  "uncertain": [
    {"filename": "fajlnev.jpg", "candidates": ["Név 1", "Név 2"], "reason": "Magyar nyelvű indoklás"}
  ],
  "unmatched_names": ["Név aki nem kapott képet"],
  "unmatched_files": ["fajlnev_ami_nem_passzol.jpg"]
}
PROMPT;

    public function __construct(
        protected ClaudeService $claudeService
    ) {}

    /**
     * Nevek és fájlok párosítása.
     *
     * @param  string[]  $names  Személyek nevei
     * @param  array<int, array{filename: string, title?: string|null, mediaId?: int}>  $files  Fájlok listája
     * @return NameMatchResult Párosítási eredmény
     *
     * @throws \Exception Ha az AI hívás sikertelen
     */
    public function match(array $names, array $files): NameMatchResult
    {
        if (empty($names) || empty($files)) {
            return new NameMatchResult(
                unmatchedNames: $names,
                unmatchedFiles: array_column($files, 'filename')
            );
        }

        $userPrompt = $this->buildPrompt($names, $files);

        Log::info('NameMatcher: Starting matching', [
            'names_count' => count($names),
            'files_count' => count($files),
        ]);

        try {
            $jsonResult = $this->claudeService->chatJson($userPrompt, self::SYSTEM_PROMPT, [
                'temperature' => 0.0,
            ]);

            $result = NameMatchResult::fromArray($jsonResult);

            // MediaId hozzáadása a matches-hez - normalizált kulcsokkal az encoding problémák miatt
            $normalizeFilename = fn($s) => preg_replace('/[^a-z0-9._-]/i', '',
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($s)))
            );

            $filesByNormalizedName = [];
            foreach ($files as $f) {
                $normalizedKey = $normalizeFilename($f['filename']);
                $filesByNormalizedName[$normalizedKey] = $f;
            }

            $matchesWithMediaId = [];

            foreach ($result->matches as $match) {
                $matchFilename = $match['filename'];
                $normalizedMatch = $normalizeFilename($matchFilename);
                $file = $filesByNormalizedName[$normalizedMatch] ?? null;

                if (!$file) {
                    Log::warning('NameMatcher: File not found for match', [
                        'looking_for' => $matchFilename,
                        'normalized' => $normalizedMatch,
                        'available_normalized' => array_keys($filesByNormalizedName),
                    ]);
                }

                $matchesWithMediaId[] = array_merge($match, [
                    'mediaId' => $file['mediaId'] ?? null,
                ]);
            }

            // Uncertain-hez is mediaId (ugyanazzal a normalizálással)
            $uncertainWithMediaId = [];
            foreach ($result->uncertain as $uncertain) {
                $normalizedUncertain = $normalizeFilename($uncertain['filename']);
                $file = $filesByNormalizedName[$normalizedUncertain] ?? null;
                $uncertainWithMediaId[] = array_merge($uncertain, [
                    'mediaId' => $file['mediaId'] ?? null,
                ]);
            }

            $finalResult = new NameMatchResult(
                matches: $matchesWithMediaId,
                uncertain: $uncertainWithMediaId,
                unmatchedNames: $result->unmatchedNames,
                unmatchedFiles: $result->unmatchedFiles,
            );

            Log::info('NameMatcher: Matching completed', [
                'matches' => $finalResult->matchCount(),
                'uncertain' => $finalResult->uncertainCount(),
                'unmatched_names' => count($finalResult->unmatchedNames),
                'unmatched_files' => count($finalResult->unmatchedFiles),
            ]);

            return $finalResult;
        } catch (\Exception $e) {
            Log::error('NameMatcher: AI matching failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Egyszerűsített hívás csak fájlnevekkel (title/mediaId nélkül).
     *
     * @param  string[]  $names  Személyek nevei
     * @param  string[]  $filenames  Fájlnevek
     */
    public function matchSimple(array $names, array $filenames): NameMatchResult
    {
        $files = array_map(fn ($f) => ['filename' => $f], $filenames);

        return $this->match($names, $files);
    }

    /**
     * Prompt összeállítása.
     */
    protected function buildPrompt(array $names, array $files): string
    {
        $namesList = implode("\n", array_map(fn ($n) => "- {$n}", $names));

        $filesList = implode("\n", array_map(function ($f) {
            $line = "- {$f['filename']}";
            if (! empty($f['title'])) {
                $line .= " (EXIF title: {$f['title']})";
            }

            return $line;
        }, $files));

        return "NEVEK ({$this->count($names)} db):\n{$namesList}\n\nFÁJLOK ({$this->count($files)} db):\n{$filesList}";
    }

    /**
     * Elemszám biztonságosan.
     */
    private function count(array $arr): int
    {
        return count($arr);
    }
}
