<?php

declare(strict_types=1);

namespace App\Services;

class FileNameMatcherService
{
    private const MIN_MATCH_THRESHOLD = 50;
    private const MAX_ALTERNATIVES = 5;
    private const AMBIGUITY_MARGIN = 10;
    private const SCORE_EXACT = 100;
    private const SCORE_WORD_REORDER = 95;
    private const SCORE_CONTAINS = 80;
    private const SCORE_WORD_OVERLAP = 75;

    /**
     * Fájlnév-lista párosítása név-listához.
     *
     * @param  string[]  $filenames  Fájlnevek (pl. 'Kiss_Anna.jpg')
     * @param  array<int, string>  $nameMap  ID => Név párok
     * @return array<int, array{filename: string, person_id: int|null, person_name: string|null, match_type: string, confidence: int, alternatives: array}>
     */
    public function matchFilenames(array $filenames, array $nameMap): array
    {
        $normalizedNames = [];
        foreach ($nameMap as $id => $name) {
            $normalizedNames[$id] = [
                'original' => $name,
                'normalized' => $this->normalizeName($name),
            ];
        }

        $results = [];
        foreach ($filenames as $filename) {
            $results[] = $this->matchSingleFile($filename, $normalizedNames);
        }

        return $results;
    }

    private function matchSingleFile(string $filename, array $normalizedNames): array
    {
        $cleanName = $this->extractNameFromFilename($filename);
        $normalizedInput = $this->normalizeName($cleanName);

        if ($normalizedInput === '') {
            return $this->buildResult($filename, null, null, 'unmatched', 0, []);
        }

        $candidates = [];

        foreach ($normalizedNames as $id => $entry) {
            $score = $this->calculateMatchScore($normalizedInput, $entry['normalized']);
            if ($score >= self::MIN_MATCH_THRESHOLD) {
                $candidates[] = [
                    'person_id' => $id,
                    'person_name' => $entry['original'],
                    'score' => $score,
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        if (count($candidates) === 0) {
            return $this->buildResult($filename, null, null, 'unmatched', 0, []);
        }

        $best = $candidates[0];
        $alternatives = array_slice($candidates, 1, self::MAX_ALTERNATIVES);

        if (count($candidates) > 1 && $candidates[1]['score'] >= $best['score'] - self::AMBIGUITY_MARGIN) {
            return $this->buildResult(
                $filename,
                $best['person_id'],
                $best['person_name'],
                'ambiguous',
                $best['score'],
                $alternatives
            );
        }

        return $this->buildResult(
            $filename,
            $best['person_id'],
            $best['person_name'],
            'matched',
            $best['score'],
            $alternatives
        );
    }

    /**
     * Fájlnévből név kinyerése.
     * "Kiss_Anna.jpg" -> "Kiss Anna"
     * "KISS ANNA 12C.jpg" -> "KISS ANNA"
     */
    private function extractNameFromFilename(string $filename): string
    {
        $name = pathinfo(basename($filename), PATHINFO_FILENAME);
        $name = str_replace(['_', '-', '.'], ' ', $name);
        $name = preg_replace('/\s+\d+[a-zA-Z]?\s*$/', '', $name) ?? $name;
        return trim($name);
    }

    /**
     * Név normalizálása összehasonlításhoz:
     * - lowercase
     * - ékezetek eltávolítása
     * - extra szóközök törlése
     */
    private function normalizeName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = $this->removeAccents($name);
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';
        return $name;
    }

    private function removeAccents(string $string): string
    {
        $map = [
            'a' => '[áàâä]',
            'e' => '[éèêë]',
            'i' => '[íìîï]',
            'o' => '[óòôöő]',
            'u' => '[úùûüű]',
        ];

        foreach ($map as $replacement => $pattern) {
            $string = preg_replace('/' . $pattern . '/u', $replacement, $string) ?? $string;
        }

        return $string;
    }

    private function calculateMatchScore(string $input, string $target): int
    {
        if ($input === $target) {
            return self::SCORE_EXACT;
        }

        // Szavak sorrendjétől független exact match
        $inputWords = $this->sortedWords($input);
        $targetWords = $this->sortedWords($target);
        if ($inputWords === $targetWords) {
            return self::SCORE_WORD_REORDER;
        }

        // Contains match: az egyik tartalmazza a másikat
        if (str_contains($target, $input) || str_contains($input, $target)) {
            return self::SCORE_CONTAINS;
        }

        // Szóátfedés vizsgálat
        $inputArr = explode(' ', $input);
        $targetArr = explode(' ', $target);
        $common = count(array_intersect($inputArr, $targetArr));
        $total = max(count($inputArr), count($targetArr));

        if ($common > 0 && $common >= $total - 1) {
            return self::SCORE_WORD_OVERLAP;
        }

        // Levenshtein alapú (byte-szintű, de removeAccents után már ASCII)
        $inputLen = strlen($input);
        $targetLen = strlen($target);
        $maxLen = max($inputLen, $targetLen);
        if ($maxLen === 0) {
            return 0;
        }

        // Early exit: ha a hosszkülönbség túl nagy, a score nem érheti el a küszöböt
        if (abs($inputLen - $targetLen) > $maxLen * 0.5) {
            return 0;
        }

        $distance = levenshtein($input, $target);
        $similarity = (int) round((1 - $distance / $maxLen) * 100);

        return max($similarity, 0);
    }

    private function sortedWords(string $str): string
    {
        $words = explode(' ', $str);
        sort($words);
        return implode(' ', $words);
    }

    private function buildResult(
        string $filename,
        ?int $personId,
        ?string $personName,
        string $matchType,
        int $confidence,
        array $alternatives
    ): array {
        return [
            'filename' => $filename,
            'person_id' => $personId,
            'person_name' => $personName,
            'match_type' => $matchType,
            'confidence' => $confidence,
            'alternatives' => array_map(fn ($a) => [
                'person_id' => $a['person_id'],
                'person_name' => $a['person_name'],
                'confidence' => $a['score'],
            ], $alternatives),
        ];
    }
}
