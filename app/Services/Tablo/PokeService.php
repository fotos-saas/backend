<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloPoke;
use App\Models\TabloPokeDailyLimit;
use App\Models\TabloPokePreset;
use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * Poke Service
 *
 * Peer-to-peer "bökés" rendszer kezelése:
 * - Bökés küldése validációkkal
 * - Napi limit kezelés
 * - Reakciók kezelése
 * - Statisztikák
 */
class PokeService
{
    // Hibaüzenetek konstansok
    public const ERROR_NOT_LOGGED_IN = 'not_logged_in';

    public const ERROR_IS_COORDINATOR = 'is_coordinator';

    public const ERROR_REGISTERED_BEFORE_YOU = 'registered_before_you';

    public const ERROR_POKED_TODAY = 'poked_today';

    public const ERROR_MAX_POKES_REACHED = 'max_pokes_reached';

    public const ERROR_DAILY_LIMIT_REACHED = 'daily_limit_reached';

    public const ERROR_SELF_POKE = 'self_poke';

    public const ERROR_BANNED = 'banned';

    public const ERROR_TARGET_BANNED = 'target_banned';

    /**
     * Bökés küldése
     *
     * @throws \InvalidArgumentException
     */
    public function sendPoke(
        TabloGuestSession $fromSession,
        TabloGuestSession $targetSession,
        string $category = TabloPoke::CATEGORY_GENERAL,
        ?string $presetKey = null,
        ?string $customMessage = null
    ): TabloPoke {
        // Validációk
        $validationError = $this->validatePoke($fromSession, $targetSession);
        if ($validationError) {
            throw new \InvalidArgumentException($validationError);
        }

        return DB::transaction(function () use ($fromSession, $targetSession, $category, $presetKey, $customMessage) {
            $emoji = null;
            $text = null;
            $messageType = 'custom';

            // Preset üzenet lekérése
            if ($presetKey) {
                $preset = TabloPokePreset::findByKey($presetKey);
                if ($preset && $preset->is_active) {
                    $emoji = $preset->emoji;
                    $text = $preset->text_hu;
                    $messageType = 'preset';
                }
            }

            // Ha custom üzenet - XSS védelem strip_tags-szel
            if (! $presetKey && $customMessage) {
                $text = strip_tags($customMessage);
                $messageType = 'custom';
            }

            // Bökés létrehozása
            $poke = TabloPoke::create([
                'from_guest_session_id' => $fromSession->id,
                'target_guest_session_id' => $targetSession->id,
                'tablo_project_id' => $fromSession->tablo_project_id,
                'category' => $category,
                'message_type' => $messageType,
                'preset_key' => $presetKey,
                'custom_message' => $customMessage,
                'emoji' => $emoji,
                'text' => $text,
                'status' => TabloPoke::STATUS_SENT,
            ]);

            // Napi limit növelése
            $dailyLimit = TabloPokeDailyLimit::getOrCreateForToday($fromSession->id);
            $dailyLimit->incrementSent();

            Log::info('Poke sent', [
                'from' => $fromSession->id,
                'to' => $targetSession->id,
                'category' => $category,
                'preset' => $presetKey,
            ]);

            // Értesítés küldése a célpontnak
            try {
                $notificationService = App::make(NotificationService::class);
                $notificationService->createPokeNotification($poke);
            } catch (\Throwable $e) {
                Log::warning('Failed to send poke notification', [
                    'poke_id' => $poke->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $poke->load(['fromSession', 'targetSession']);
        });
    }

    /**
     * Bökés validálása
     *
     * Opcionálisan cache-elt értékeket is elfogad az N+1 optimalizáláshoz.
     * Ha nem adunk meg cache-t, akkor frissen lekérdezi az adatokat.
     *
     * @param  TabloGuestSession  $fromSession  Küldő session
     * @param  TabloGuestSession  $targetSession  Célpont session
     * @param  array|null  $cachedData  Opcionális cache: ['totalPokes' => int, 'pokedToday' => bool, 'dailyLimitReached' => bool]
     * @return string|null Hibaüzenet kulcs vagy null ha sikeres
     */
    public function validatePoke(
        TabloGuestSession $fromSession,
        TabloGuestSession $targetSession,
        ?array $cachedData = null
    ): ?string {
        // Önmagának nem lehet bökni
        if ($fromSession->id === $targetSession->id) {
            return self::ERROR_SELF_POKE;
        }

        // Küldő nem lehet bannolt
        if ($fromSession->is_banned) {
            return self::ERROR_BANNED;
        }

        // Célpont nem lehet bannolt
        if ($targetSession->is_banned) {
            return self::ERROR_TARGET_BANNED;
        }

        // Célpont még nem jelentkezett be (nincs last_activity_at)
        // Ezt a frontenden kezeljük, backend-en nem blokkoló

        // Extra tagok (tanárok, koordinátorok) nem bökhetők
        if ($targetSession->is_extra) {
            return self::ERROR_IS_COORDINATOR;
        }

        // Cache-elt vagy friss értékek használata
        if ($cachedData !== null) {
            $pokedToday = $cachedData['pokedToday'];
            $totalPokes = $cachedData['totalPokes'];
            $dailyLimitReached = $cachedData['dailyLimitReached'];
        } else {
            // Ma már bökte ezt a felhasználót
            $pokedToday = TabloPoke::sentBy($fromSession->id)
                ->receivedBy($targetSession->id)
                ->today()
                ->exists();

            // Összesen hányszor bökte
            $totalPokes = TabloPoke::sentBy($fromSession->id)
                ->receivedBy($targetSession->id)
                ->count();

            // Napi limit ellenőrzés
            $dailyCount = TabloPokeDailyLimit::getTodayCount($fromSession->id);
            $dailyLimitReached = $dailyCount >= TabloPoke::DAILY_LIMIT;
        }

        // TESZT: Kikommentelve a napi és összesített limit
        // if ($pokedToday) {
        //     return self::ERROR_POKED_TODAY;
        // }

        // if ($totalPokes >= TabloPoke::MAX_POKES_PER_USER) {
        //     return self::ERROR_MAX_POKES_REACHED;
        // }

        if ($dailyLimitReached) {
            return self::ERROR_DAILY_LIMIT_REACHED;
        }

        return null;
    }

    /**
     * Ellenőrzi, hogy bökhető-e a célpont (frontend számára)
     */
    public function canPoke(TabloGuestSession $fromSession, TabloGuestSession $targetSession): array
    {
        $error = $this->validatePoke($fromSession, $targetSession);

        return self::createPokeStatus($error === null, $error);
    }

    /**
     * Egységes poke státusz array létrehozása
     *
     * Használandó mindenhol ahol poke státuszt adunk vissza a frontendnek.
     *
     * @param  bool  $canPoke  Bökhető-e
     * @param  string|null  $reason  Hiba kulcs (ERROR_* konstans) vagy null ha bökhető
     * @param  int  $totalSent  Eddigi bökések száma erre a célpontra
     * @return array{can_poke: bool, reason: ?string, reason_hu: ?string, total_pokes_sent: int, max_pokes: int}
     */
    public static function createPokeStatus(bool $canPoke, ?string $reason, int $totalSent = 0): array
    {
        return [
            'can_poke' => $canPoke,
            'reason' => $reason,
            'reason_hu' => $reason ? self::getErrorMessageHuStatic($reason) : null,
            'total_pokes_sent' => $totalSent,
            'max_pokes' => TabloPoke::MAX_POKES_PER_USER,
        ];
    }

    /**
     * Magyar nyelvű hibaüzenet (statikus verzió helper számára)
     */
    public static function getErrorMessageHuStatic(string $error): string
    {
        return match ($error) {
            self::ERROR_NOT_LOGGED_IN => 'A felhasználó még nem jelentkezett be.',
            self::ERROR_IS_COORDINATOR => 'Koordinátorokat és tanárokat nem lehet bökni.',
            self::ERROR_REGISTERED_BEFORE_YOU => 'Csak nálad korábban regisztrált felhasználókat bökhetsz.',
            self::ERROR_POKED_TODAY => 'Ma már bökted ezt a felhasználót.',
            self::ERROR_MAX_POKES_REACHED => 'Ezt a felhasználót már 3x bökted.',
            self::ERROR_DAILY_LIMIT_REACHED => 'Elérted a napi bökés limitet.',
            self::ERROR_SELF_POKE => 'Önmagadat nem bökheted.',
            self::ERROR_BANNED => 'A bökés nem engedélyezett.',
            self::ERROR_TARGET_BANNED => 'Ez a felhasználó le van tiltva.',
            'no_session' => 'Nincs bejelentkezve.',
            'not_found' => 'Felhasználó nem található.',
            default => 'Ismeretlen hiba.',
        };
    }

    /**
     * Magyar nyelvű hibaüzenet
     */
    protected function getErrorMessageHu(string $error): string
    {
        return match ($error) {
            self::ERROR_NOT_LOGGED_IN => 'A felhasználó még nem jelentkezett be.',
            self::ERROR_IS_COORDINATOR => 'Koordinátorokat és tanárokat nem lehet bökni.',
            self::ERROR_REGISTERED_BEFORE_YOU => 'Csak nálad korábban regisztrált felhasználókat bökhetsz.',
            self::ERROR_POKED_TODAY => 'Ma már bökted ezt a felhasználót.',
            self::ERROR_MAX_POKES_REACHED => 'Ezt a felhasználót már 3x bökted.',
            self::ERROR_DAILY_LIMIT_REACHED => 'Elérted a napi bökés limitet.',
            self::ERROR_SELF_POKE => 'Önmagadat nem bökheted.',
            self::ERROR_BANNED => 'A bökés nem engedélyezett.',
            self::ERROR_TARGET_BANNED => 'Ez a felhasználó le van tiltva.',
            default => 'Ismeretlen hiba.',
        };
    }

    /**
     * Reakció hozzáadása
     */
    public function addReaction(TabloPoke $poke, string $reaction): TabloPoke
    {
        if (! in_array($reaction, TabloPoke::REACTIONS)) {
            throw new \InvalidArgumentException('Érvénytelen reakció.');
        }

        $poke->addReaction($reaction);

        Log::info('Poke reaction added', [
            'poke_id' => $poke->id,
            'reaction' => $reaction,
        ]);

        // Értesítés küldése a bökés küldőjének
        try {
            $notificationService = App::make(NotificationService::class);
            $notificationService->createPokeReactionNotification($poke);
        } catch (\Throwable $e) {
            Log::warning('Failed to send poke reaction notification', [
                'poke_id' => $poke->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $poke->fresh();
    }

    /**
     * Olvasottnak jelölés
     */
    public function markAsRead(TabloPoke $poke): TabloPoke
    {
        $poke->markAsRead();

        return $poke;
    }

    /**
     * Összes kapott bökés olvasottnak jelölése
     */
    public function markAllAsRead(TabloGuestSession $session): int
    {
        return TabloPoke::receivedBy($session->id)
            ->unread()
            ->update(['is_read' => true]);
    }

    /**
     * Küldött bökések lekérése
     * Projekt szintű izoláció biztosítva
     */
    public function getSentPokes(TabloGuestSession $session, int $limit = 50): Collection
    {
        return TabloPoke::sentBy($session->id)
            ->forProject($session->tablo_project_id)
            ->with(['targetSession', 'fromSession'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Kapott bökések lekérése
     * Projekt szintű izoláció biztosítva
     */
    public function getReceivedPokes(TabloGuestSession $session, int $limit = 50): Collection
    {
        return TabloPoke::receivedBy($session->id)
            ->forProject($session->tablo_project_id)
            ->with(['fromSession', 'targetSession'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Olvasatlan bökések száma
     */
    public function getUnreadCount(TabloGuestSession $session): int
    {
        return TabloPoke::receivedBy($session->id)
            ->unread()
            ->count();
    }

    /**
     * Napi limit információ
     */
    public function getDailyLimitInfo(TabloGuestSession $session): array
    {
        $dailyLimit = TabloPokeDailyLimit::getOrCreateForToday($session->id);

        return [
            'sent_today' => $dailyLimit->pokes_sent,
            'daily_limit' => TabloPoke::DAILY_LIMIT,
            'remaining' => $dailyLimit->getRemainingPokes(),
            'has_reached_limit' => $dailyLimit->hasReachedLimit(),
        ];
    }

    /**
     * Preset üzenetek lekérése
     */
    public function getPresets(?string $category = null): Collection
    {
        return TabloPokePreset::getActivePresets($category);
    }

    /**
     * Bökés statisztikák projekthez
     */
    public function getProjectStats(TabloProject $project): array
    {
        $totalPokes = TabloPoke::forProject($project->id)->count();
        $todayPokes = TabloPoke::forProject($project->id)->today()->count();
        $unreadPokes = TabloPoke::forProject($project->id)->unread()->count();

        return [
            'total_pokes' => $totalPokes,
            'today_pokes' => $todayPokes,
            'unread_pokes' => $unreadPokes,
        ];
    }

    /**
     * Adott felhasználó bökhető-e (lista előszűréshez)
     */
    public function getPokeStatus(TabloGuestSession $fromSession, TabloGuestSession $targetSession): array
    {
        // Hányszor bökte eddig
        $totalPokes = TabloPoke::sentBy($fromSession->id)
            ->receivedBy($targetSession->id)
            ->count();

        $error = $this->validatePoke($fromSession, $targetSession);

        return self::createPokeStatus($error === null, $error, $totalPokes);
    }

    /**
     * Batch poke státusz lekérdezés - N+1 optimalizálás
     * Egy query-vel lekérdezi az összes target session poke státuszát
     *
     * @param  TabloGuestSession  $fromSession  A küldő session
     * @param  array<int>  $targetSessionIds  A célpont session ID-k
     * @return array<int, array{can_poke: bool, reason: ?string, reason_hu: ?string, total_pokes_sent: int, max_pokes: int}>
     */
    public function getBatchPokeStatus(TabloGuestSession $fromSession, array $targetSessionIds): array
    {
        if (empty($targetSessionIds)) {
            return [];
        }

        // Egyetlen lekérdezéssel: hányszor bökte az egyes felhasználókat
        $pokeCounts = TabloPoke::sentBy($fromSession->id)
            ->whereIn('target_guest_session_id', $targetSessionIds)
            ->selectRaw('target_guest_session_id, COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END) as today_count')
            ->groupBy('target_guest_session_id')
            ->get()
            ->keyBy('target_guest_session_id');

        // Napi limit ellenőrzés (egyszer)
        $dailyCount = TabloPokeDailyLimit::getTodayCount($fromSession->id);
        $dailyLimitReached = $dailyCount >= TabloPoke::DAILY_LIMIT;

        // Target session-ök lekérése (egyetlen query)
        $targetSessions = TabloGuestSession::whereIn('id', $targetSessionIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($targetSessionIds as $targetId) {
            $targetSession = $targetSessions->get($targetId);

            if (! $targetSession) {
                $result[$targetId] = self::createPokeStatus(false, 'not_found', 0);
                continue;
            }

            $pokeData = $pokeCounts->get($targetId);
            $totalPokes = $pokeData?->total_count ?? 0;
            $pokedToday = ($pokeData?->today_count ?? 0) > 0;

            // Validáció (cache-elt értékekkel)
            $error = $this->validatePoke(
                $fromSession,
                $targetSession,
                [
                    'totalPokes' => $totalPokes,
                    'pokedToday' => $pokedToday,
                    'dailyLimitReached' => $dailyLimitReached,
                ]
            );

            $result[$targetId] = self::createPokeStatus($error === null, $error, $totalPokes);
        }

        return $result;
    }

}
