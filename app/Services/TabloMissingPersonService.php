<?php

namespace App\Services;

use App\Enums\TabloPersonType;
use App\Models\TabloMissingPerson;
use Illuminate\Support\Facades\Cache;

/**
 * Tabló hiányzó személyek kezelése - badge count-ok és csoportosítás
 */
class TabloMissingPersonService
{
    private const CACHE_TTL = 300; // 5 perc

    private const CACHE_PREFIX = 'tablo_missing_counts_';

    /**
     * Tab badge számlálók - 1 optimalizált query-vel, cache-elve
     *
     * @return array{teachers: int, students: int, all: int}
     */
    public function getTabCounts(?int $projectId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . ($projectId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($projectId) {
            $counts = TabloMissingPerson::query()
                ->when($projectId, fn ($q) => $q->where('tablo_project_id', $projectId))
                ->selectRaw('
                    SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as teachers,
                    SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as students,
                    COUNT(*) as total
                ', [TabloPersonType::TEACHER->value, TabloPersonType::STUDENT->value])
                ->first();

            return [
                'teachers' => (int) ($counts->teachers ?? 0),
                'students' => (int) ($counts->students ?? 0),
                'all' => (int) ($counts->total ?? 0),
            ];
        });
    }

    /**
     * Cache invalidálás - hívandó amikor TabloMissingPerson módosul
     */
    public function clearCountCache(?int $projectId = null): void
    {
        if ($projectId) {
            Cache::forget(self::CACHE_PREFIX . $projectId);
        }
        Cache::forget(self::CACHE_PREFIX . 'all');
    }

    /**
     * Összes cache törlése (pl. bulk műveletek után)
     */
    public function clearAllCountCache(): void
    {
        // Redis pattern delete lenne ideális, de file/database driver-rel ez működik
        Cache::forget(self::CACHE_PREFIX . 'all');

        // Projekt-specifikus cache-eket a következő lekérdezésnél újratöltjük
        // Nincs pattern delete file driver-rel, így project cache-ek TTL-lel járnak le
    }

    /**
     * Csoportosítás meghatározása tab és filter alapján
     */
    public function getGroupingForTab(?string $tab, ?int $projectId): ?string
    {
        // Projekt filter esetén mindig típus szerinti csoportosítás
        if ($projectId) {
            return 'type:asc';
        }

        return match ($tab) {
            'teachers' => 'project.school.name:asc',
            'students' => 'tablo_project_id:asc',
            default => null,
        };
    }

    /**
     * Típus alapján egyes számú címke
     */
    public function getTypeLabel(string $type): string
    {
        $enum = TabloPersonType::tryFrom($type);

        return $enum?->label() ?? 'Ismeretlen';
    }

    /**
     * Típus alapján többes számú címke
     */
    public function getTypePluralLabel(string $type): string
    {
        $enum = TabloPersonType::tryFrom($type);

        return $enum?->pluralLabel() ?? 'Ismeretlenek';
    }
}
