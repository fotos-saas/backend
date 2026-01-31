<?php

namespace App\DTOs;

/**
 * Fotó párosítás teljes eredménye.
 * Tartalmazza a sikeres, bizonytalan és orphan (talon) képeket is.
 */
readonly class PhotoMatchResult
{
    /**
     * @param  array<int, array{
     *     person_id: int,
     *     person_name: string,
     *     media_id: int,
     *     filename: string,
     *     project_id: int,
     *     project_name?: string,
     *     confidence: string
     * }>  $matches  Sikeres párosítások
     * @param  array<int, array{
     *     filename: string,
     *     candidates: string[],
     *     reason: string,
     *     media_id?: int,
     *     project_id?: int
     * }>  $uncertain  Bizonytalan esetek (több jelölt)
     * @param  array<int, array{
     *     filename: string,
     *     media_id?: int,
     *     suggested_name?: string,
     *     reason?: string
     * }>  $orphans  Talon képek (nincs párosítás)
     */
    public function __construct(
        public array $matches = [],
        public array $uncertain = [],
        public array $orphans = [],
    ) {}

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
     * Talon képek száma.
     */
    public function orphanCount(): int
    {
        return count($this->orphans);
    }

    /**
     * Összes feldolgozott kép száma.
     */
    public function totalCount(): int
    {
        return $this->matchCount() + $this->uncertainCount() + $this->orphanCount();
    }

    /**
     * Van-e bizonytalan eset?
     */
    public function hasUncertain(): bool
    {
        return $this->uncertainCount() > 0;
    }

    /**
     * Van-e orphan kép?
     */
    public function hasOrphans(): bool
    {
        return $this->orphanCount() > 0;
    }

    /**
     * Minden sikeresen párosult?
     */
    public function isFullyMatched(): bool
    {
        return $this->uncertainCount() === 0 && $this->orphanCount() === 0;
    }

    /**
     * Összefoglaló szöveg magyarul.
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->matchCount() > 0) {
            $parts[] = $this->matchCount() . ' sikeres párosítás';
        }

        if ($this->uncertainCount() > 0) {
            $parts[] = $this->uncertainCount() . ' bizonytalan';
        }

        if ($this->orphanCount() > 0) {
            $parts[] = $this->orphanCount() . ' talon képnek jelölve';
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
            'orphans' => $this->orphans,
            'summary' => [
                'match_count' => $this->matchCount(),
                'uncertain_count' => $this->uncertainCount(),
                'orphan_count' => $this->orphanCount(),
                'total_count' => $this->totalCount(),
            ],
        ];
    }

    /**
     * Merge másik eredménnyel.
     */
    public function merge(PhotoMatchResult $other): self
    {
        return new self(
            matches: array_merge($this->matches, $other->matches),
            uncertain: array_merge($this->uncertain, $other->uncertain),
            orphans: array_merge($this->orphans, $other->orphans),
        );
    }
}
