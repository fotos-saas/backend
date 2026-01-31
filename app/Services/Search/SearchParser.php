<?php

namespace App\Services\Search;

/**
 * Google-szerű keresési query tokenizálása.
 *
 * Támogatott formátumok:
 * - #123 → ID keresés
 * - @név → Prefix keresés (ügyintéző)
 * - "pontos kifejezés" → Phrase keresés
 * - szabad szavak → Szó keresés
 * - Kombinálva: "Árpád Gimnázium" Tatabánya → Mindkettő kell (AND)
 */
class SearchParser
{
    protected string $rawQuery;
    protected ?int $id = null;
    protected ?string $prefix = null;
    protected ?string $prefixValue = null;
    protected array $phrases = [];
    protected array $words = [];

    public static function parse(string $query): self
    {
        return new self(trim($query));
    }

    protected function __construct(string $query)
    {
        $this->rawQuery = $query;
        $this->tokenize();
    }

    protected function tokenize(): void
    {
        // 1. ID keresés (#123)
        if (preg_match('/^#(\d+)$/', $this->rawQuery, $matches)) {
            $this->id = (int) $matches[1];
            return;
        }

        // 2. Prefix keresés (@név) - de NEM email cím
        // Email cím: valami@domain.hu - tartalmaz @ jelet, de utána pont is van
        if (preg_match('/^@(.+)$/u', $this->rawQuery, $matches)) {
            // Ha az @ után pont van, valószínűleg email cím, nem prefix
            if (!preg_match('/\.[a-z]{2,}$/i', $matches[1])) {
                $this->prefix = '@';
                $this->prefixValue = $matches[1];
                return;
            }
        }

        // 3. Idézőjeles kifejezések kinyerése
        if (preg_match_all('/"([^"]+)"/u', $this->rawQuery, $matches)) {
            $this->phrases = array_filter(array_map('trim', $matches[1]));
        }

        // 4. Maradék szavak (idézőjelek eltávolítása után)
        $remaining = trim(preg_replace('/"[^"]*"/u', '', $this->rawQuery));
        if ($remaining !== '') {
            $this->words = array_filter(
                preg_split('/\s+/u', $remaining),
                fn($w) => strlen($w) > 0
            );
        }

        // Ha nincs phrase és nincs word, az eredeti keresést használjuk
        if (empty($this->phrases) && empty($this->words)) {
            $this->words = [$this->rawQuery];
        }
    }

    public function hasIdSearch(): bool
    {
        return $this->id !== null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function hasPrefix(): bool
    {
        return $this->prefix !== null;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getPrefixValue(): ?string
    {
        return $this->prefixValue;
    }

    public function getPhrases(): array
    {
        return $this->phrases;
    }

    public function getWords(): array
    {
        return $this->words;
    }

    /**
     * Összes keresési term (phrases + words).
     * Minden term-nek AND kapcsolatban kell teljesülnie.
     */
    public function getAllTerms(): array
    {
        return array_merge($this->phrases, $this->words);
    }

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
