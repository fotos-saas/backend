<?php

namespace App\DTOs;

/**
 * Név-fájlnév párosítás eredménye.
 */
readonly class NameMatchResult
{
    /**
     * @param  array<int, array{name: string, filename: string, confidence: string, mediaId?: int}>  $matches  Sikeres párosítások
     * @param  array<int, array{filename: string, candidates: string[], reason: string, mediaId?: int}>  $uncertain  Bizonytalan esetek
     * @param  string[]  $unmatchedNames  Párosítatlan nevek
     * @param  string[]  $unmatchedFiles  Párosítatlan fájlok
     */
    public function __construct(
        public array $matches = [],
        public array $uncertain = [],
        public array $unmatchedNames = [],
        public array $unmatchedFiles = [],
    ) {}

    /**
     * Factory method JSON array-ből.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            matches: $data['matches'] ?? [],
            uncertain: $data['uncertain'] ?? [],
            unmatchedNames: $data['unmatched_names'] ?? [],
            unmatchedFiles: $data['unmatched_files'] ?? [],
        );
    }

    /**
     * Sikeres párosítások száma.
     */
    public function matchCount(): int
    {
        return count($this->matches);
    }

    /**
     * Bizonytalan esetek száma.
     */
    public function uncertainCount(): int
    {
        return count($this->uncertain);
    }

    /**
     * Van-e bizonytalan eset?
     */
    public function hasUncertain(): bool
    {
        return $this->uncertainCount() > 0;
    }

    /**
     * Összefoglaló szöveg magyarul.
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->matchCount() > 0) {
            $parts[] = "{$this->matchCount()} sikeres párosítás";
        }

        if ($this->uncertainCount() > 0) {
            $parts[] = "{$this->uncertainCount()} bizonytalan";
        }

        if (count($this->unmatchedNames) > 0) {
            $parts[] = count($this->unmatchedNames).' név kép nélkül';
        }

        if (count($this->unmatchedFiles) > 0) {
            $parts[] = count($this->unmatchedFiles).' kép név nélkül';
        }

        return implode(', ', $parts) ?: 'Nincs eredmény';
    }

    /**
     * Array-ként exportálás.
     */
    public function toArray(): array
    {
        return [
            'matches' => $this->matches,
            'uncertain' => $this->uncertain,
            'unmatched_names' => $this->unmatchedNames,
            'unmatched_files' => $this->unmatchedFiles,
        ];
    }
}
