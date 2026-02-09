<?php

namespace App\Services\Help;

use App\Models\HelpTour;
use App\Models\HelpTourProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HelpTourService
{
    /**
     * Elérhető túrák lekérése route+role+user alapján.
     */
    public function getAvailableTours(string $route, string $role, int $userId): Collection
    {
        $tours = $this->getToursForRoute($route, $role);

        if ($tours->isEmpty()) {
            return collect();
        }

        // Szűrés: user még nem fejezte be / nem skipelte
        $completedTourIds = HelpTourProgress::where('user_id', $userId)
            ->whereIn('status', ['completed', 'skipped'])
            ->pluck('help_tour_id')
            ->toArray();

        return $tours->filter(fn (HelpTour $tour) => ! in_array($tour->id, $completedTourIds));
    }

    /**
     * Túra haladás frissítése.
     */
    public function updateProgress(int $userId, int $tourId, string $status, int $stepNumber): HelpTourProgress
    {
        $progress = HelpTourProgress::updateOrCreate(
            ['user_id' => $userId, 'help_tour_id' => $tourId],
            [
                'status' => $status,
                'last_step_number' => $stepNumber,
                'completed_at' => $status === 'completed' ? now() : null,
            ]
        );

        return $progress;
    }

    /**
     * User összes túra haladása.
     */
    public function getUserProgress(int $userId): Collection
    {
        return HelpTourProgress::where('user_id', $userId)
            ->with('tour')
            ->get();
    }

    /**
     * Túrák lekérése route+role alapján (cache-elve).
     */
    private function getToursForRoute(string $route, string $role): Collection
    {
        $cacheKey = "help:tours:route:".md5("{$route}:{$role}");

        return Cache::remember($cacheKey, 900, function () use ($route, $role) {
            return HelpTour::active()
                ->forRoute($route)
                ->where(function ($q) use ($role) {
                    $q->whereJsonContains('target_roles', $role)
                        ->orWhereJsonLength('target_roles', 0);
                })
                ->with('steps')
                ->get();
        });
    }
}
