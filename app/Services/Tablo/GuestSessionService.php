<?php

namespace App\Services\Tablo;

use App\Models\TabloGuestSession;
use App\Models\TabloMissingPerson;
use App\Models\TabloProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Guest Session Service
 *
 * Vendég session kezelés:
 * - Regisztráció névvel
 * - Session validálás
 * - Cross-device session link
 * - Ban/unban funkciók
 */
class GuestSessionService
{
    /**
     * Session expiry napok (inaktivitás)
     */
    private const SESSION_EXPIRY_DAYS = 30;

    /**
     * Regisztrál egy új vendég session-t
     */
    public function register(
        TabloProject $project,
        string $guestName,
        ?string $guestEmail = null,
        ?string $deviceIdentifier = null,
        ?string $ipAddress = null
    ): TabloGuestSession {
        // Ellenőrizzük, hogy van-e már session ezzel az email-lel
        if ($guestEmail) {
            $existing = TabloGuestSession::where('tablo_project_id', $project->id)
                ->where('guest_email', $guestEmail)
                ->first();

            if ($existing) {
                // Frissítjük a meglévő session-t
                $existing->update([
                    'guest_name' => $guestName,
                    'device_identifier' => $deviceIdentifier ?? $existing->device_identifier,
                    'ip_address' => $ipAddress ?? $existing->ip_address,
                    'last_activity_at' => now(),
                ]);

                Log::info('Guest session updated via email', [
                    'project_id' => $project->id,
                    'session_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        // Új session létrehozása
        $session = TabloGuestSession::create([
            'tablo_project_id' => $project->id,
            'session_token' => (string) Str::uuid(),
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'device_identifier' => $deviceIdentifier,
            'ip_address' => $ipAddress,
            'last_activity_at' => now(),
        ]);

        // Frissítjük a projekt vendég számot
        $project->updateGuestsCount();

        Log::info('New guest session created', [
            'project_id' => $project->id,
            'session_id' => $session->id,
        ]);

        return $session;
    }

    /**
     * Validál egy session tokent
     */
    public function validate(string $sessionToken, int $projectId): ?TabloGuestSession
    {
        $session = TabloGuestSession::where('session_token', $sessionToken)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return null;
        }

        // Ellenőrizzük, hogy nincs-e bannolva
        if ($session->is_banned) {
            Log::warning('Banned guest attempted access', [
                'project_id' => $projectId,
                'session_id' => $session->id,
            ]);

            return null;
        }

        // Ellenőrizzük az inaktivitási időt
        if ($session->last_activity_at && $session->last_activity_at->diffInDays(now()) > self::SESSION_EXPIRY_DAYS) {
            Log::info('Guest session expired due to inactivity', [
                'project_id' => $projectId,
                'session_id' => $session->id,
            ]);

            return null;
        }

        return $session;
    }

    /**
     * Frissíti a session aktivitási idejét (heartbeat)
     */
    public function heartbeat(TabloGuestSession $session, ?string $ipAddress = null): void
    {
        $updates = ['last_activity_at' => now()];

        if ($ipAddress && $session->ip_address !== $ipAddress) {
            $updates['ip_address'] = $ipAddress;
        }

        $session->update($updates);
    }

    /**
     * Cross-device session link generálás
     */
    public function generateDeviceLink(TabloGuestSession $session, TabloProject $project): string
    {
        $baseUrl = config('app.frontend_tablo_url');
        $shareToken = $project->share_token;

        return "{$baseUrl}/share/{$shareToken}?session={$session->session_token}";
    }

    /**
     * Session lekérése token alapján (cross-device)
     */
    public function getByToken(string $sessionToken): ?TabloGuestSession
    {
        return TabloGuestSession::where('session_token', $sessionToken)
            ->where('is_banned', false)
            ->first();
    }

    /**
     * Vendég bannolása
     */
    public function ban(TabloGuestSession $session): void
    {
        $session->update(['is_banned' => true]);

        Log::info('Guest session banned', [
            'project_id' => $session->tablo_project_id,
            'session_id' => $session->id,
            'guest_name' => $session->guest_name,
        ]);

        // Frissítjük a projekt vendég számot
        $session->project?->updateGuestsCount();
    }

    /**
     * Vendég ban feloldása
     */
    public function unban(TabloGuestSession $session): void
    {
        $session->update(['is_banned' => false]);

        Log::info('Guest session unbanned', [
            'project_id' => $session->tablo_project_id,
            'session_id' => $session->id,
            'guest_name' => $session->guest_name,
        ]);

        // Frissítjük a projekt vendég számot
        $session->project?->updateGuestsCount();
    }

    /**
     * Projekt vendégek listája
     */
    public function getGuestsByProject(TabloProject $project, bool $includeBanned = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = $project->guestSessions();

        if (! $includeBanned) {
            $query->where('is_banned', false);
        }

        return $query->orderByDesc('last_activity_at')->get();
    }

    /**
     * Aktív vendégek száma (utolsó 24 óra)
     */
    public function getActiveGuestsCount(TabloProject $project): int
    {
        return $project->guestSessions()
            ->where('is_banned', false)
            ->where('last_activity_at', '>=', now()->subHours(24))
            ->count();
    }

    /**
     * Vendég statisztikák
     */
    public function getGuestStatistics(TabloProject $project): array
    {
        $sessions = $project->guestSessions();

        return [
            'total' => $sessions->count(),
            'active' => $sessions->clone()->where('is_banned', false)->count(),
            'banned' => $sessions->clone()->where('is_banned', true)->count(),
            'extra_count' => $sessions->clone()->where('is_extra', true)->count(),
            'regular_count' => $sessions->clone()->where('is_extra', false)->where('is_banned', false)->count(),
            'active_24h' => $this->getActiveGuestsCount($project),
            'expected_class_size' => $project->expected_class_size,
            'participation_rate' => $project->getPollParticipationRate(),
        ];
    }

    /**
     * Régi, inaktív sessionök törlése
     */
    public function cleanupExpiredSessions(): int
    {
        $cutoffDate = now()->subDays(self::SESSION_EXPIRY_DAYS * 2);

        $deleted = TabloGuestSession::where('last_activity_at', '<', $cutoffDate)
            ->orWhereNull('last_activity_at')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        if ($deleted > 0) {
            Log::info('Expired guest sessions cleaned up', ['count' => $deleted]);
        }

        return $deleted;
    }

    /**
     * Session token átadása új eszközre
     */
    public function transferSession(
        TabloGuestSession $session,
        ?string $newDeviceIdentifier = null,
        ?string $newIpAddress = null
    ): TabloGuestSession {
        $session->update([
            'device_identifier' => $newDeviceIdentifier,
            'ip_address' => $newIpAddress,
            'last_activity_at' => now(),
        ]);

        Log::info('Guest session transferred to new device', [
            'project_id' => $session->tablo_project_id,
            'session_id' => $session->id,
        ]);

        return $session;
    }

    // ==========================================
    // IDENTIFICATION (ONBOARDING)
    // ==========================================

    /**
     * Hiányzó személyek keresése autocomplete-hez
     *
     * @param  string  $query  Keresett szöveg
     * @param  int  $limit  Maximum találatok száma
     * @return Collection<TabloMissingPerson>
     */
    public function searchMissingPersons(TabloProject $project, string $query, int $limit = 10): Collection
    {
        if (strlen($query) < 2) {
            return collect();
        }

        // Keresés név alapján, ILIKE-kal (case-insensitive)
        return $project->missingPersons()
            ->where('name', 'ILIKE', '%'.trim($query).'%')
            ->orderBy('type') // student first, then teacher
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (TabloMissingPerson $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'type' => $person->type,
                'type_label' => $person->type_label,
                'has_photo' => $person->hasPhoto(),
                'is_claimed' => $person->isClaimed(),
            ]);
    }

    /**
     * Regisztráció személyazonosítással (onboarding flow)
     *
     * @return array{session: TabloGuestSession, has_conflict: bool, conflict_message: string|null}
     */
    public function registerWithIdentification(
        TabloProject $project,
        string $nickname,
        ?int $missingPersonId = null,
        ?string $email = null,
        ?string $deviceIdentifier = null,
        ?string $ipAddress = null
    ): array {
        return DB::transaction(function () use ($project, $nickname, $missingPersonId, $email, $deviceIdentifier, $ipAddress) {
            $hasConflict = false;
            $conflictMessage = null;
            $verificationStatus = TabloGuestSession::VERIFICATION_VERIFIED;

            // Ha van kiválasztott személy, ellenőrizzük az ütközést
            if ($missingPersonId) {
                $missingPerson = TabloMissingPerson::find($missingPersonId);

                if ($missingPerson && $missingPerson->isClaimed()) {
                    // Ütközés: már van verified session ehhez a személyhez
                    $hasConflict = true;
                    $verificationStatus = TabloGuestSession::VERIFICATION_PENDING;
                    $conflictMessage = 'Ezt a nevet már valaki használja. Az ügyintéző hamarosan jóváhagyja a kérésedet.';

                    Log::info('Guest registration conflict detected', [
                        'project_id' => $project->id,
                        'missing_person_id' => $missingPersonId,
                        'existing_session_id' => $missingPerson->guestSession?->id,
                    ]);
                }
            }

            // Ellenőrizzük, hogy van-e már session ezzel az email-lel (és verified)
            if ($email) {
                $existingByEmail = TabloGuestSession::where('tablo_project_id', $project->id)
                    ->where('guest_email', $email)
                    ->verified()
                    ->first();

                if ($existingByEmail) {
                    // Email alapján frissítjük a meglévő session-t
                    $existingByEmail->update([
                        'guest_name' => $nickname,
                        'tablo_missing_person_id' => $missingPersonId,
                        'device_identifier' => $deviceIdentifier ?? $existingByEmail->device_identifier,
                        'ip_address' => $ipAddress ?? $existingByEmail->ip_address,
                        'last_activity_at' => now(),
                    ]);

                    Log::info('Guest session updated via email during onboarding', [
                        'project_id' => $project->id,
                        'session_id' => $existingByEmail->id,
                    ]);

                    return [
                        'session' => $existingByEmail,
                        'has_conflict' => false,
                        'conflict_message' => null,
                    ];
                }
            }

            // Új session létrehozása
            $session = TabloGuestSession::create([
                'tablo_project_id' => $project->id,
                'session_token' => (string) Str::uuid(),
                'guest_name' => $nickname,
                'guest_email' => $email,
                'tablo_missing_person_id' => $missingPersonId,
                'verification_status' => $verificationStatus,
                'device_identifier' => $deviceIdentifier,
                'ip_address' => $ipAddress,
                'last_activity_at' => now(),
            ]);

            // Ha nem pending, frissítjük a projekt vendég számot
            if (! $hasConflict) {
                $project->updateGuestsCount();
            }

            Log::info('New guest session created via onboarding', [
                'project_id' => $project->id,
                'session_id' => $session->id,
                'has_conflict' => $hasConflict,
                'verification_status' => $verificationStatus,
            ]);

            return [
                'session' => $session,
                'has_conflict' => $hasConflict,
                'conflict_message' => $conflictMessage,
            ];
        });
    }

    /**
     * Session státusz ellenőrzése (polling endpoint-hoz)
     */
    public function checkVerificationStatus(TabloGuestSession $session): array
    {
        // Frissítjük a session-t az adatbázisból
        $session->refresh();

        return [
            'verification_status' => $session->verification_status,
            'is_verified' => $session->isVerified(),
            'is_pending' => $session->isPending(),
            'is_rejected' => $session->isRejected(),
            'is_banned' => $session->is_banned,
            'missing_person_name' => $session->missingPerson?->name,
        ];
    }

    /**
     * Ütközés feloldása (admin által)
     * Az új igénylő session-jét verified státuszra állítja,
     * és a régi session-ről leveszi a missing_person_id-t
     *
     * @param  TabloGuestSession|int  $pendingSession  Session instance or ID
     * @param  bool|string  $action  true/'approve' for approve, false/'reject' for reject
     * @return array{success: bool, message: string, session: TabloGuestSession|null}
     */
    public function resolveConflict(TabloGuestSession|int $pendingSession, bool|string $action): array
    {
        // Normalize inputs
        if (is_int($pendingSession)) {
            $pendingSession = TabloGuestSession::find($pendingSession);
            if (! $pendingSession) {
                return [
                    'success' => false,
                    'message' => 'Session nem található.',
                    'session' => null,
                ];
            }
        }

        if (! $pendingSession->isPending()) {
            return [
                'success' => false,
                'message' => 'Ez a session nem pending státuszú.',
                'session' => $pendingSession,
            ];
        }

        $approve = is_string($action) ? $action === 'approve' : $action;

        $session = DB::transaction(function () use ($pendingSession, $approve) {
            if ($approve) {
                // Megkeressük a régi verified session-t ugyanahhoz a személyhez
                $oldSession = TabloGuestSession::where('tablo_project_id', $pendingSession->tablo_project_id)
                    ->where('tablo_missing_person_id', $pendingSession->tablo_missing_person_id)
                    ->where('id', '!=', $pendingSession->id)
                    ->verified()
                    ->first();

                if ($oldSession) {
                    // A régi session-ről levesszük a párosítást
                    $oldSession->update([
                        'tablo_missing_person_id' => null,
                    ]);

                    Log::info('Old session unlinked from missing person', [
                        'old_session_id' => $oldSession->id,
                        'missing_person_id' => $pendingSession->tablo_missing_person_id,
                    ]);
                }

                // Az új session-t verified-re állítjuk
                $pendingSession->update([
                    'verification_status' => TabloGuestSession::VERIFICATION_VERIFIED,
                ]);

                Log::info('Pending session approved', [
                    'session_id' => $pendingSession->id,
                ]);
            } else {
                // Elutasítás: rejected státusz, de a session megmarad (név nélkül)
                $pendingSession->update([
                    'verification_status' => TabloGuestSession::VERIFICATION_REJECTED,
                    'tablo_missing_person_id' => null, // Levesszük a párosítást
                ]);

                Log::info('Pending session rejected', [
                    'session_id' => $pendingSession->id,
                ]);
            }

            // Frissítjük a projekt vendég számot
            $pendingSession->project?->updateGuestsCount();

            return $pendingSession;
        });

        return [
            'success' => true,
            'message' => $approve ? 'Session jóváhagyva.' : 'Session elutasítva.',
            'session' => $session,
        ];
    }

    /**
     * Pending státuszú session-ök listája egy projekthez (admin felülethez)
     */
    public function getPendingSessions(TabloProject $project): \Illuminate\Database\Eloquent\Collection
    {
        return $project->guestSessions()
            ->pending()
            ->with('missingPerson')
            ->orderByDesc('created_at')
            ->get();
    }
}
