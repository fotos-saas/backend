<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPerson;
use App\Models\TabloPoke;
use App\Models\TabloPoll;
use App\Models\TabloPollVote;
use App\Models\TabloProject;
use Illuminate\Support\Collection;

/**
 * Missing User Service
 *
 * Hiányzó felhasználók lekérdezése különböző kategóriákban:
 * - Szavazás: nem szavazott még
 * - Fotózás: nincs fotója
 * - Képválasztás: nem választott képet
 */
class MissingUserService
{
    public function __construct(
        protected PokeService $pokeService
    ) {}

    /**
     * Összes hiányzó felhasználó lekérése kategóriánként
     */
    public function getMissingUsers(TabloProject $project, ?TabloGuestSession $currentSession = null): array
    {
        return [
            'voting' => $this->getMissingForVoting($project, $currentSession),
            'photoshoot' => $this->getMissingForPhotoshoot($project, $currentSession),
            'image_selection' => $this->getMissingForImageSelection($project, $currentSession),
        ];
    }

    /**
     * Szavazásból hiányzók
     * Azok akik még egyetlen aktív szavazáson sem szavaztak
     */
    public function getMissingForVoting(TabloProject $project, ?TabloGuestSession $currentSession = null): array
    {
        // Aktív szavazások lekérése
        $activePolls = $project->polls()->active()->get();

        if ($activePolls->isEmpty()) {
            return [
                'count' => 0,
                'users' => [],
                'has_active_poll' => false,
            ];
        }

        // Összes session aki regisztrált (nem banned, nem extra)
        $allSessions = $project->guestSessions()
            ->regularMembers()
            ->get();

        // Kik szavaztak már legalább egy aktív szavazáson
        $voterIds = TabloPollVote::whereIn('tablo_poll_id', $activePolls->pluck('id'))
            ->distinct()
            ->pluck('tablo_guest_session_id')
            ->toArray();

        // Hiányzók: akik nem szavaztak
        $missingUsers = $allSessions->filter(fn ($session) => ! in_array($session->id, $voterIds));

        return [
            'count' => $missingUsers->count(),
            'users' => $this->formatUsersWithPokeStatus($missingUsers, $currentSession, TabloPoke::CATEGORY_VOTING),
            'has_active_poll' => true,
            'active_polls_count' => $activePolls->count(),
        ];
    }

    /**
     * Fotózásból hiányzók
     * Azok akiknek nincs fotója (tablo_persons alapján)
     * MINDEN hiányzó személy megjelenik, függetlenül attól van-e guest session-jük
     * N+1 optimalizált verzió
     */
    public function getMissingForPhotoshoot(TabloProject $project, ?TabloGuestSession $currentSession = null): array
    {
        // Missing persons akiknek nincs fotója
        $missingPersonsWithoutPhoto = $project->persons()
            ->whereNull('media_id')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        // Batch lekérdezés: összes guest session aki direkt párosítva van
        $directMatchSessions = $project->guestSessions()
            ->regularMembers()
            ->verified()
            ->whereIn('tablo_person_id', $missingPersonsWithoutPhoto->pluck('id'))
            ->get()
            ->keyBy('tablo_person_id');

        // Gyűjtsük össze a guest session ID-kat a batch poke státuszhoz
        $guestSessionIds = $directMatchSessions->pluck('id')->toArray();

        // Batch poke státusz lekérdezés (ha van current session)
        $batchPokeStatus = [];
        if ($currentSession && ! empty($guestSessionIds)) {
            $batchPokeStatus = $this->pokeService->getBatchPokeStatus($currentSession, $guestSessionIds);
        }

        // Formázzuk a hiányzó személyeket
        $formattedUsers = $missingPersonsWithoutPhoto->map(function (TabloPerson $person) use ($directMatchSessions, $currentSession, $batchPokeStatus) {
            // Keresünk egyező guest session-t a cache-ből
            $guestSession = $directMatchSessions->get($person->id);

            return $this->formatMissingPersonOptimized($person, $guestSession, $currentSession, $batchPokeStatus);
        })->values()->toArray();

        return [
            'count' => count($formattedUsers),
            'users' => $formattedUsers,
            'total_missing_photos' => $missingPersonsWithoutPhoto->count(),
        ];
    }

    /**
     * Guest session keresése egy missing person-höz
     * PRIORITÁS: 1) tablo_person_id párosítás, 2) név/email ILIKE fallback
     */
    protected function findGuestSessionForPerson(TabloProject $project, TabloPerson $person): ?TabloGuestSession
    {
        // 1. Először a direkt párosítást keressük (verified státuszú)
        $directMatch = $project->guestSessions()
            ->regularMembers()
            ->verified()
            ->where('tablo_person_id', $person->id)
            ->first();

        if ($directMatch) {
            return $directMatch;
        }

        // 2. Fallback: név/email ILIKE keresés (régi logika, kompatibilitás miatt)
        return $project->guestSessions()
            ->regularMembers()
            ->verified()
            ->where(function ($query) use ($person) {
                $query->where('guest_name', 'ILIKE', $person->name);
                if ($person->email) {
                    $query->orWhere('guest_email', $person->email);
                }
            })
            ->first();
    }


    /**
     * Missing person formázása bökés státusszal - N+1 optimalizált verzió
     * Batch poke státuszt használ a már lekérdezett adatokból
     */
    protected function formatMissingPersonOptimized(
        TabloPerson $person,
        ?TabloGuestSession $guestSession,
        ?TabloGuestSession $currentSession,
        array $batchPokeStatus
    ): array {
        $hasGuestSession = $guestSession !== null;

        $formatted = [
            'id' => $person->id,  // TabloPerson ID
            'name' => $person->name,
            'email' => $person->email,
            'type' => $person->type,  // 'student' | 'teacher'
            'has_guest_session' => $hasGuestSession,
            'guest_session_id' => $guestSession?->id,
            'last_activity_at' => $guestSession?->last_activity_at?->toIso8601String(),
            'has_activity' => $guestSession?->last_activity_at !== null,
        ];

        // Bökés státusz meghatározása (batch eredményből)
        if (! $hasGuestSession) {
            $formatted['poke_status'] = PokeService::createPokeStatus(false, PokeService::ERROR_NOT_LOGGED_IN, 0);
        } elseif (! $currentSession) {
            $formatted['poke_status'] = PokeService::createPokeStatus(false, 'no_session', 0);
        } elseif ($currentSession->id === $guestSession->id) {
            $formatted['poke_status'] = PokeService::createPokeStatus(false, PokeService::ERROR_SELF_POKE, 0);
        } else {
            // Batch eredményből vesszük
            $formatted['poke_status'] = $batchPokeStatus[$guestSession->id]
                ?? PokeService::createPokeStatus(false, 'unknown', 0);
        }

        return $formatted;
    }

    /**
     * Képválasztásból hiányzók
     * Ez projekt-specifikus lehet - alapértelmezetten üres
     */
    public function getMissingForImageSelection(TabloProject $project, ?TabloGuestSession $currentSession = null): array
    {
        // A képválasztás logika projekt-specifikus
        // Alapesetben ez üres, de kiterjeszthető
        return [
            'count' => 0,
            'users' => [],
            'message' => 'Nincs képválasztási feladat',
        ];
    }

    /**
     * Felhasználók formázása bökés státusszal
     * N+1 optimalizált verzió - batch lekérdezéssel
     */
    protected function formatUsersWithPokeStatus(
        Collection $users,
        ?TabloGuestSession $currentSession,
        string $category
    ): array {
        // Ha nincs current session, egyszerűsített verzió
        if (! $currentSession) {
            return $users->map(function (TabloGuestSession $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->guest_name,
                    'email' => $user->guest_email,
                    'is_extra' => $user->is_extra,
                    'last_activity_at' => $user->last_activity_at?->toIso8601String(),
                    'has_activity' => $user->last_activity_at !== null,
                    'created_at' => $user->created_at->toIso8601String(),
                    'poke_status' => PokeService::createPokeStatus(false, 'no_session', 0),
                ];
            })->values()->toArray();
        }

        // Batch poke státusz lekérdezés (N+1 optimalizálás)
        $userIds = $users->pluck('id')->filter(fn ($id) => $id !== $currentSession->id)->toArray();
        $batchPokeStatus = $this->pokeService->getBatchPokeStatus($currentSession, $userIds);

        return $users->map(function (TabloGuestSession $user) use ($currentSession, $batchPokeStatus) {
            $formatted = [
                'id' => $user->id,
                'name' => $user->guest_name,
                'email' => $user->guest_email,
                'is_extra' => $user->is_extra,
                'last_activity_at' => $user->last_activity_at?->toIso8601String(),
                'has_activity' => $user->last_activity_at !== null,
                'created_at' => $user->created_at->toIso8601String(),
            ];

            // Bökés státusz batch eredményből
            if ($currentSession->id === $user->id) {
                $formatted['poke_status'] = PokeService::createPokeStatus(false, PokeService::ERROR_SELF_POKE, 0);
            } else {
                $formatted['poke_status'] = $batchPokeStatus[$user->id]
                    ?? PokeService::createPokeStatus(false, 'unknown', 0);
            }

            return $formatted;
        })->values()->toArray();
    }

    /**
     * Összesített hiányzó statisztika
     */
    public function getMissingSummary(TabloProject $project): array
    {
        $voting = $this->getMissingForVoting($project);
        $photoshoot = $this->getMissingForPhotoshoot($project);
        $imageSelection = $this->getMissingForImageSelection($project);

        $totalMissing = collect([
            $voting['users'] ?? [],
            $photoshoot['users'] ?? [],
            $imageSelection['users'] ?? [],
        ])->flatten(1)->pluck('id')->unique()->count();

        return [
            'total_missing' => $totalMissing,
            'by_category' => [
                'voting' => $voting['count'] ?? 0,
                'photoshoot' => $photoshoot['count'] ?? 0,
                'image_selection' => $imageSelection['count'] ?? 0,
            ],
        ];
    }

    /**
     * Egy felhasználó hiányzásai
     */
    public function getUserMissingStatus(TabloGuestSession $session): array
    {
        $project = $session->project;

        $status = [
            'missing_voting' => false,
            'missing_photoshoot' => false,
            'missing_image_selection' => false,
        ];

        // Szavazás ellenőrzés
        $activePolls = $project->polls()->active()->get();
        if ($activePolls->isNotEmpty()) {
            $hasVoted = TabloPollVote::whereIn('tablo_poll_id', $activePolls->pluck('id'))
                ->where('tablo_guest_session_id', $session->id)
                ->exists();
            $status['missing_voting'] = ! $hasVoted;
        }

        // Fotózás ellenőrzés
        // PRIORITÁS: 1) tablo_person_id párosítás, 2) név/email ILIKE fallback
        $hasMissingPhoto = false;

        if ($session->tablo_person_id) {
            // Direkt párosítás - ellenőrizzük a missing person fotó státuszát
            $hasMissingPhoto = $project->persons()
                ->whereNull('media_id')
                ->where('id', $session->tablo_person_id)
                ->exists();
        } else {
            // Fallback: név/email ILIKE keresés
            $hasMissingPhoto = $project->persons()
                ->whereNull('media_id')
                ->where(function ($query) use ($session) {
                    $query->where('name', 'ILIKE', $session->guest_name);
                    if ($session->guest_email) {
                        $query->orWhere('email', $session->guest_email);
                    }
                })
                ->exists();
        }

        $status['missing_photoshoot'] = $hasMissingPhoto;

        return $status;
    }
}
