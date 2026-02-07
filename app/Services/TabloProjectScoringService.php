<?php

namespace App\Services;

use App\Enums\TabloProjectStatus;
use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TabloProjectScoringService
{
    // Scoring weights (összesen 100)
    private const WEIGHT_SCHOOL_PENETRATION = 35;
    private const WEIGHT_YEAR_PENETRATION = 15;
    private const WEIGHT_STATUS = 25;
    private const WEIGHT_MISSING_PHOTOS = 10;
    private const WEIGHT_CONTACT_ACTIVITY = 10;
    private const WEIGHT_EMAIL = 5;

    // Kapcsolattartó aktivitás: ennyi napon belüli kontakt számít "frissnek"
    private const CONTACT_ACTIVITY_DAYS = 14;

    // Cache TTL percben
    private const CACHE_TTL_MINUTES = 30;

    // Prioritás határok
    public const PRIORITY_TOP = 'top';           // >= 60
    public const PRIORITY_MEDIUM = 'medium';     // 30-59
    public const PRIORITY_LOW = 'low';           // < 30

    /**
     * Státusz pontok (25 pontból)
     */
    private function getStatusScore(TabloProjectStatus $status): int
    {
        return match ($status) {
            TabloProjectStatus::WaitingForFinalization => 25,    // Majdnem kész!
            TabloProjectStatus::AtTeacherForFinalization => 22,  // Tanárnál van
            TabloProjectStatus::SosWaitingForPhotos => 20,       // SOS - sürgős!
            TabloProjectStatus::PushCouldBeDone => 20,           // Kész lehetne
            TabloProjectStatus::ShouldFinish => 18,              // Be kellene fejezni
            TabloProjectStatus::WaitingForPhotos => 15,          // Képekre vár
            TabloProjectStatus::GotResponse => 12,               // Kaptam választ
            TabloProjectStatus::NeedsCall => 10,                 // Hívni kell
            TabloProjectStatus::WaitingForResponse => 8,         // Várok válaszra
            TabloProjectStatus::NeedsForwarding => 5,            // Tovább kell küldeni
            TabloProjectStatus::NotStarted => 0,                 // Még nem kezdtük
            TabloProjectStatus::InPrint => 0,                    // Nyomdában - kész
            TabloProjectStatus::Done => 0,                       // Kész
        };
    }

    /**
     * Számold ki egy projekt össz pontszámát
     */
    public function calculateScore(TabloProject $project): array
    {
        $scores = [
            'school_penetration' => $this->calculateSchoolPenetrationScore($project),
            'year_penetration' => $this->calculateYearPenetrationScore($project),
            'status' => $this->calculateStatusScore($project),
            'missing_photos' => $this->calculateMissingPhotosScore($project),
            'contact_activity' => $this->calculateContactActivityScore($project),
            'email' => $this->calculateEmailScore($project),
        ];

        $total = array_sum($scores);

        return [
            'total' => $total,
            'priority' => $this->getPriorityFromScore($total),
            'breakdown' => $scores,
        ];
    }

    /**
     * Iskolai penetráció: hány osztály aktív az iskolában?
     * Max 35 pont
     */
    private function calculateSchoolPenetrationScore(TabloProject $project): float
    {
        if (!$project->school_id) {
            return 0;
        }

        $cacheKey = "school_penetration_{$project->school_id}_{$project->class_year}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($project) {
            // Összes projekt az iskolában (azonos évfolyammal)
            $query = TabloProject::where('school_id', $project->school_id);

            if ($project->class_year) {
                $query->where('class_year', $project->class_year);
            }

            $totalInSchool = $query->count();

            if ($totalInSchool <= 1) {
                return 0; // Ha egyedül van, nincs penetráció
            }

            // Aktív projektek (nem not_started és nem done/in_print)
            $activeInSchool = (clone $query)
                ->whereNotIn('status', [
                    TabloProjectStatus::NotStarted->value,
                    TabloProjectStatus::Done->value,
                    TabloProjectStatus::InPrint->value,
                ])
                ->count();

            // Penetráció arány (0-1)
            $penetrationRatio = $activeInSchool / $totalInSchool;

            return round($penetrationRatio * self::WEIGHT_SCHOOL_PENETRATION, 1);
        });
    }

    /**
     * Évfolyam penetráció: országosan hány projekt aktív az évfolyamban?
     * Max 15 pont
     */
    private function calculateYearPenetrationScore(TabloProject $project): float
    {
        if (!$project->class_year) {
            return 0;
        }

        $cacheKey = "year_penetration_{$project->class_year}_{$project->partner_id}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($project) {
            // Összes projekt az évfolyamban (partner szerint szűrve)
            $totalInYear = TabloProject::where('class_year', $project->class_year)
                ->where('partner_id', $project->partner_id)
                ->count();

            if ($totalInYear <= 1) {
                return 0;
            }

            // Aktív projektek
            $activeInYear = TabloProject::where('class_year', $project->class_year)
                ->where('partner_id', $project->partner_id)
                ->whereNotIn('status', [
                    TabloProjectStatus::NotStarted->value,
                    TabloProjectStatus::Done->value,
                    TabloProjectStatus::InPrint->value,
                ])
                ->count();

            $penetrationRatio = $activeInYear / $totalInYear;

            return round($penetrationRatio * self::WEIGHT_YEAR_PENETRATION, 1);
        });
    }

    /**
     * Státusz pont
     * Max 25 pont
     */
    private function calculateStatusScore(TabloProject $project): int
    {
        return $this->getStatusScore($project->status);
    }

    /**
     * Hiányzó képek pont: ha nincs hiányzó VAGY mind megvan → pont
     * Max 10 pont
     */
    private function calculateMissingPhotosScore(TabloProject $project): int
    {
        $missingPersons = $project->persons()->count();

        if ($missingPersons === 0) {
            return self::WEIGHT_MISSING_PHOTOS; // Nincs hiányzó → teljes pont
        }

        // Van hiányzó - nézzük meg, hánynak van képe
        $withPhoto = $project->persons()
            ->whereNotNull('media_id')
            ->count();

        if ($withPhoto === $missingPersons) {
            return self::WEIGHT_MISSING_PHOTOS; // Mind megvan → teljes pont
        }

        // Részleges pont a meglevő arányban
        $ratio = $withPhoto / $missingPersons;

        return (int) round($ratio * self::WEIGHT_MISSING_PHOTOS);
    }

    /**
     * Kapcsolattartó aktivitás: friss kontakt = pont
     * Max 10 pont
     */
    private function calculateContactActivityScore(TabloProject $project): int
    {
        $recentContact = $project->contacts()
            ->where('last_contacted_at', '>=', now()->subDays(self::CONTACT_ACTIVITY_DAYS))
            ->exists();

        return $recentContact ? self::WEIGHT_CONTACT_ACTIVITY : 0;
    }

    /**
     * Email státusz: megválaszolatlan bejövő = pont
     * Max 5 pont
     */
    private function calculateEmailScore(TabloProject $project): int
    {
        $hasUnansweredInbound = $project->emails()
            ->where('direction', 'inbound')
            ->where('needs_reply', true)
            ->where('is_replied', false)
            ->exists();

        return $hasUnansweredInbound ? self::WEIGHT_EMAIL : 0;
    }

    /**
     * Prioritás meghatározása pontszám alapján
     */
    public function getPriorityFromScore(float $score): string
    {
        if ($score >= 60) {
            return self::PRIORITY_TOP;
        }

        if ($score >= 30) {
            return self::PRIORITY_MEDIUM;
        }

        return self::PRIORITY_LOW;
    }

    /**
     * Prioritás magyar címke
     */
    public function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_TOP => '🔴 TOP - Azonnal!',
            self::PRIORITY_MEDIUM => '🟡 Közepes',
            self::PRIORITY_LOW => '🟢 Elvan',
            default => 'Ismeretlen',
        };
    }

    /**
     * Prioritás szín (Filament badge-hez)
     */
    public function getPriorityColor(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_TOP => 'danger',
            self::PRIORITY_MEDIUM => 'warning',
            self::PRIORITY_LOW => 'success',
            default => 'gray',
        };
    }

    /**
     * Összes javasolt projekt lekérése (nem done/in_print)
     * Score szerint rendezve
     */
    public function getSuggestedProjects(?int $partnerId = null): Collection
    {
        $query = TabloProject::query()
            ->with(['school', 'partner', 'contacts', 'persons', 'emails'])
            ->whereNotIn('status', [
                TabloProjectStatus::Done->value,
                TabloProjectStatus::InPrint->value,
            ]);

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        $projects = $query->get();

        // Pontszám számítása és rendezés
        return $projects->map(function (TabloProject $project) {
            $scoreData = $this->calculateScore($project);
            $project->priority_score = $scoreData['total'];
            $project->priority = $scoreData['priority'];
            $project->score_breakdown = $scoreData['breakdown'];

            return $project;
        })->sortByDesc('priority_score')->values();
    }

    /**
     * Projektek lekérése prioritás szerint
     */
    public function getProjectsByPriority(string $priority, ?int $partnerId = null): Collection
    {
        return $this->getSuggestedProjects($partnerId)
            ->filter(fn (TabloProject $project) => $project->priority === $priority)
            ->values();
    }

    /**
     * Projekt prioritás összesítés (dashboard-hoz)
     */
    public function getPrioritySummary(?int $partnerId = null): array
    {
        $projects = $this->getSuggestedProjects($partnerId);

        return [
            self::PRIORITY_TOP => $projects->where('priority', self::PRIORITY_TOP)->count(),
            self::PRIORITY_MEDIUM => $projects->where('priority', self::PRIORITY_MEDIUM)->count(),
            self::PRIORITY_LOW => $projects->where('priority', self::PRIORITY_LOW)->count(),
            'total' => $projects->count(),
        ];
    }

    /**
     * Cache törlése (pl. projekt módosítás után)
     */
    public function clearCache(?int $schoolId = null, ?string $classYear = null): void
    {
        if ($schoolId && $classYear) {
            Cache::forget("school_penetration_{$schoolId}_{$classYear}");
        }

        if ($classYear) {
            // Összes partner year cache törlése
            $partnerIds = TabloProject::distinct()->pluck('partner_id');
            foreach ($partnerIds as $partnerId) {
                Cache::forget("year_penetration_{$classYear}_{$partnerId}");
            }
        }
    }
}
