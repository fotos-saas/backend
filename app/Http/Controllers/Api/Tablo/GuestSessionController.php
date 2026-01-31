<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Mail\GuestSessionRestoreMail;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Services\Tablo\GuestSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Guest Session Controller
 *
 * Vendég session kezelés API végpontok.
 * Token-ból azonosítja a projektet.
 */
class GuestSessionController extends Controller
{
    public function __construct(
        protected GuestSessionService $guestSessionService
    ) {}

    /**
     * Register new guest session.
     * POST /api/tablo-frontend/guest/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_name' => 'required|string|max:100|min:2',
            'guest_email' => 'nullable|email|max:255',
            'device_identifier' => 'nullable|string|max:255',
        ], [
            'guest_name.required' => 'A név megadása kötelező.',
            'guest_name.min' => 'A név legalább 2 karakter legyen.',
            'guest_email.email' => 'Érvénytelen email cím.',
        ]);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $session = $this->guestSessionService->register(
            $project,
            $validated['guest_name'],
            $validated['guest_email'] ?? null,
            $validated['device_identifier'] ?? null,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Sikeres regisztráció!',
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
            ],
        ]);
    }

    /**
     * Validate existing session.
     * POST /api/tablo-frontend/guest/validate
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = $this->guestSessionService->validate(
            $validated['session_token'],
            $projectId
        );

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Érvénytelen vagy lejárt session',
                'valid' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
            ],
        ]);
    }

    /**
     * Send device link via email.
     * POST /api/tablo-frontend/guest/send-link
     */
    public function sendLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
            'email' => 'required|email|max:255',
        ], [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
        ]);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $session = TabloGuestSession::where('session_token', $validated['session_token'])
            ->where('tablo_project_id', $project->id)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session nem található',
            ], 404);
        }

        // Update email if different
        if ($session->guest_email !== $validated['email']) {
            $session->update(['guest_email' => $validated['email']]);
        }

        // Generate device link
        $link = $this->guestSessionService->generateDeviceLink($session, $project);

        // TODO: Send email with link
        // Mail::to($validated['email'])->send(new GuestDeviceLinkMail($session, $link));

        return response()->json([
            'success' => true,
            'message' => 'Link elküldve a megadott email címre!',
            // Debug: return link in development
            'link' => config('app.debug') ? $link : null,
        ]);
    }

    /**
     * Heartbeat - update activity.
     * POST /api/tablo-frontend/guest/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('session_token', $validated['session_token'])
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session nem található',
            ], 404);
        }

        $this->guestSessionService->heartbeat($session, $request->ip());

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get guests list (contact only).
     * GET /api/tablo-frontend/admin/guests
     */
    public function getGuests(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $includeBanned = $request->boolean('include_banned', true);
        $guests = $this->guestSessionService->getGuestsByProject($project, $includeBanned);

        // Aktuális guest ID lekérése X-Guest-Session headerből
        $currentGuestId = null;
        $guestSessionToken = $request->header('X-Guest-Session');
        if ($guestSessionToken) {
            $currentGuest = TabloGuestSession::where('session_token', $guestSessionToken)
                ->where('tablo_project_id', $project->id)
                ->first();
            $currentGuestId = $currentGuest?->id;
        }

        return response()->json([
            'success' => true,
            'data' => $guests->map(fn ($guest) => [
                'id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'guest_email' => $guest->guest_email,
                'is_banned' => $guest->is_banned,
                'is_extra' => $guest->is_extra,
                'last_activity_at' => $guest->last_activity_at?->toIso8601String(),
                'created_at' => $guest->created_at->toIso8601String(),
                'votes_count' => $guest->votes()->count(),
            ]),
            'statistics' => $this->guestSessionService->getGuestStatistics($project),
            'current_guest_id' => $currentGuestId,
        ]);
    }

    /**
     * Ban guest (contact only).
     * POST /api/tablo-frontend/admin/guests/{id}/ban
     */
    public function ban(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $this->guestSessionService->ban($session);

        return response()->json([
            'success' => true,
            'message' => 'Vendég sikeresen tiltva!',
        ]);
    }

    /**
     * Unban guest (contact only).
     * POST /api/tablo-frontend/admin/guests/{id}/unban
     */
    public function unban(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $this->guestSessionService->unban($session);

        return response()->json([
            'success' => true,
            'message' => 'Vendég tiltása feloldva!',
        ]);
    }

    /**
     * Toggle extra status (contact only).
     * PUT /api/tablo-frontend/admin/guests/{id}/extra
     */
    public function toggleExtra(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $session->toggleExtra();

        return response()->json([
            'success' => true,
            'message' => $session->is_extra
                ? 'Vendég extra tagként jelölve!'
                : 'Extra jelölés eltávolítva!',
            'is_extra' => $session->is_extra,
        ]);
    }

    /**
     * Update guest info (name and/or email).
     * PUT /api/tablo-frontend/guest/update
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
            'guest_name' => 'required|string|max:100|min:2',
            'guest_email' => 'nullable|email|max:255',
        ], [
            'session_token.required' => 'Session token szükséges.',
            'guest_name.required' => 'A név megadása kötelező.',
            'guest_name.min' => 'A név legalább 2 karakter legyen.',
            'guest_email.email' => 'Érvénytelen email cím.',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('session_token', $validated['session_token'])
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session nem található',
            ], 404);
        }

        // Update guest info
        $session->update([
            'guest_name' => $validated['guest_name'],
            'guest_email' => $validated['guest_email'] ?? null,
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Adatok sikeresen frissítve!',
            'data' => [
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
            ],
        ]);
    }

    /**
     * Check session status (for polling).
     * GET /api/tablo-frontend/guest/session-status
     *
     * Lightweight endpoint for frontend polling to detect:
     * - Banned sessions (403)
     * - Deleted sessions (401)
     * - Valid sessions (200)
     */
    public function sessionStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        // First check if session exists at all (including banned)
        $session = TabloGuestSession::where('session_token', $validated['session_token'])
            ->where('tablo_project_id', $projectId)
            ->first();

        // Session deleted
        if (! $session) {
            return response()->json([
                'valid' => false,
                'reason' => 'deleted',
                'message' => 'A munkamenet törölve lett.',
            ], 401);
        }

        // Session banned
        if ($session->is_banned) {
            return response()->json([
                'valid' => false,
                'reason' => 'banned',
                'message' => 'Hozzáférés megtagadva. Kérlek vedd fel a kapcsolatot a szervezőkkel.',
            ], 403);
        }

        // Session valid
        return response()->json([
            'valid' => true,
        ]);
    }

    /**
     * Search participants for @mentions.
     * GET /api/tablo-frontend/participants/search?q=keresés
     *
     * Returns guests and contacts that match the search query.
     */
    public function searchParticipants(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->integer('limit', 10), 20);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $results = [];

        // Search guests (not banned)
        if (strlen($query) >= 1) {
            $guests = TabloGuestSession::where('tablo_project_id', $project->id)
                ->where('is_banned', false)
                ->where('guest_name', 'ilike', '%' . $query . '%')
                ->orderBy('guest_name')
                ->limit($limit)
                ->get();

            foreach ($guests as $guest) {
                $results[] = [
                    'id' => 'guest_' . $guest->id,
                    'type' => 'guest',
                    'name' => $guest->guest_name,
                    'display' => $guest->guest_name,
                ];
            }
        }

        // Add contact if query matches and there's room
        $contact = $project->contact;
        if ($contact && count($results) < $limit) {
            $contactName = $contact->name ?? 'Kapcsolattartó';
            if (empty($query) || stripos($contactName, $query) !== false) {
                $results[] = [
                    'id' => 'contact_' . $contact->id,
                    'type' => 'contact',
                    'name' => $contactName,
                    'display' => $contactName . ' (Kapcsolattartó)',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Set expected class size (contact only).
     * PUT /api/tablo-frontend/admin/class-size
     */
    public function setClassSize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expected_class_size' => 'required|integer|min:1|max:500',
        ], [
            'expected_class_size.required' => 'A létszám megadása kötelező.',
            'expected_class_size.min' => 'A létszám legalább 1 legyen.',
            'expected_class_size.max' => 'A létszám maximum 500 lehet.',
        ]);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $project->update([
            'expected_class_size' => $validated['expected_class_size'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Osztálylétszám sikeresen beállítva!',
            'data' => [
                'expected_class_size' => $project->expected_class_size,
            ],
        ]);
    }

    // ==========================================
    // ONBOARDING - IDENTIFICATION
    // ==========================================

    /**
     * Search missing persons for autocomplete.
     * GET /api/tablo-frontend/guest/missing-persons/search?q=keresés
     *
     * Tablón szereplő személyek keresése az onboarding flow-hoz.
     */
    public function searchMissingPersons(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min($request->integer('limit', 10), 20);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $results = $this->guestSessionService->searchMissingPersons($project, $query, $limit);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Register with identification (onboarding flow).
     * POST /api/tablo-frontend/guest/register-with-identification
     *
     * Regisztráció személy kiválasztással és becenévvel.
     */
    public function registerWithIdentification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nickname' => 'required|string|max:100|min:2',
            'missing_person_id' => 'nullable|integer|exists:tablo_missing_persons,id',
            'email' => 'required|email|max:255',
            'device_identifier' => 'nullable|string|max:255',
        ], [
            'nickname.required' => 'A becenév megadása kötelező.',
            'nickname.min' => 'A becenév legalább 2 karakter legyen.',
            'missing_person_id.exists' => 'A kiválasztott személy nem található.',
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
        ]);

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Ha van missing_person_id, ellenőrizzük hogy a projekt missing_persons listájában van-e
        if ($validated['missing_person_id'] ?? null) {
            $validPerson = $project->missingPersons()
                ->where('id', $validated['missing_person_id'])
                ->exists();

            if (! $validPerson) {
                return response()->json([
                    'success' => false,
                    'message' => 'A kiválasztott személy nem tartozik ehhez a projekthez.',
                ], 422);
            }
        }

        $result = $this->guestSessionService->registerWithIdentification(
            $project,
            $validated['nickname'],
            $validated['missing_person_id'] ?? null,
            $validated['email'] ?? null,
            $validated['device_identifier'] ?? null,
            $request->ip()
        );

        $session = $result['session'];

        return response()->json([
            'success' => true,
            'message' => $result['has_conflict']
                ? $result['conflict_message']
                : 'Sikeres regisztráció!',
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
                'verification_status' => $session->verification_status,
                'is_pending' => $session->isPending(),
                'missing_person_id' => $session->tablo_missing_person_id,
                'missing_person_name' => $session->missingPerson?->name,
            ],
            'has_conflict' => $result['has_conflict'],
        ]);
    }

    /**
     * Check verification status (polling endpoint).
     * GET /api/tablo-frontend/guest/verification-status
     *
     * Pending státuszú session-ök polling-ja az ütközés feloldásáig.
     */
    public function checkVerificationStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string|uuid',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('session_token', $validated['session_token'])
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session nem található',
            ], 404);
        }

        $status = $this->guestSessionService->checkVerificationStatus($session);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Resolve conflict (admin only).
     * POST /api/tablo-frontend/admin/guests/{id}/resolve-conflict
     *
     * Pending státuszú session jóváhagyása vagy elutasítása.
     */
    public function resolveConflict(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'approve' => 'required|boolean',
        ], [
            'approve.required' => 'A döntés megadása kötelező.',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->pending()
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Pending session nem található',
            ], 404);
        }

        $resolvedSession = $this->guestSessionService->resolveConflict($session, $validated['approve']);

        return response()->json([
            'success' => true,
            'message' => $validated['approve']
                ? 'Kérés jóváhagyva!'
                : 'Kérés elutasítva!',
            'data' => [
                'id' => $resolvedSession->id,
                'verification_status' => $resolvedSession->verification_status,
                'guest_name' => $resolvedSession->guest_name,
            ],
        ]);
    }

    /**
     * Get pending sessions (admin only).
     * GET /api/tablo-frontend/admin/pending-sessions
     *
     * Pending státuszú session-ök listája ütközéskezeléshez.
     */
    public function getPendingSessions(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $pendingSessions = $this->guestSessionService->getPendingSessions($project);

        return response()->json([
            'success' => true,
            'data' => $pendingSessions->map(fn ($session) => [
                'id' => $session->id,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
                'missing_person' => $session->missingPerson ? [
                    'id' => $session->missingPerson->id,
                    'name' => $session->missingPerson->name,
                    'type' => $session->missingPerson->type,
                    'type_label' => $session->missingPerson->type_label,
                ] : null,
                'created_at' => $session->created_at->toIso8601String(),
                // Megmutatjuk ki az eredeti "tulajdonos"
                'existing_owner' => $session->missingPerson?->guestSession ? [
                    'id' => $session->missingPerson->guestSession->id,
                    'guest_name' => $session->missingPerson->guestSession->guest_name,
                ] : null,
            ]),
            'count' => $pendingSessions->count(),
        ]);
    }

    // ==========================================
    // SESSION RESTORE (MAGIC LINK)
    // ==========================================

    /**
     * Request restore link via email.
     * POST /api/tablo-frontend/guest/request-restore-link
     *
     * Korábban regisztrált vendég session visszaállítása emailben küldött magic linkkel.
     */
    public function requestRestoreLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ], [
            'email.required' => 'Az email cím megadása kötelező.',
            'email.email' => 'Érvénytelen email cím.',
        ]);

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;
        $project = TabloProject::find($projectId);

        if (! $project) {
            // Biztonsági okokból azonos üzenetet adunk vissza
            return response()->json([
                'success' => true,
                'message' => 'Ha létezik fiók ezzel az email címmel, linket küldtünk.',
            ]);
        }

        // Rate limiting: max 3 kérés / óra / email + projekt kombináció
        $rateLimitKey = "restore_link:{$projectId}:{$validated['email']}";
        $attempts = (int) Cache::get($rateLimitKey, 0);

        if ($attempts >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Túl sok kérés. Kérjük próbáld újra később.',
            ], 429);
        }

        // Keresés email alapján
        $session = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('guest_email', $validated['email'])
            ->verified()
            ->active()
            ->first();

        if (! $session) {
            // Biztonsági okokból NE mondjuk meg, hogy nem létezik
            // De növeljük a rate limit számlálót
            Cache::put($rateLimitKey, $attempts + 1, now()->addHour());

            return response()->json([
                'success' => true,
                'message' => 'Ha létezik fiók ezzel az email címmel, linket küldtünk.',
            ]);
        }

        // Rate limit növelése
        Cache::put($rateLimitKey, $attempts + 1, now()->addHour());

        // Restore token generálás
        $restoreToken = Str::random(64);
        $session->update([
            'restore_token' => $restoreToken,
            'restore_token_expires_at' => now()->addHours(24),
        ]);

        // Frontend URL összeállítása
        $frontendUrl = rtrim(config('app.frontend_tablo_url', config('app.url')), '/');
        $restoreLink = "{$frontendUrl}/share/{$project->share_token}?restore={$restoreToken}";

        // Email küldés
        try {
            Mail::to($validated['email'])->send(
                new GuestSessionRestoreMail($session, $project, $restoreLink)
            );

            \Log::info('[GuestSession] Restore link sent', [
                'project_id' => $projectId,
                'session_id' => $session->id,
                'email' => $validated['email'],
            ]);
        } catch (\Exception $e) {
            \Log::error('[GuestSession] Failed to send restore link', [
                'project_id' => $projectId,
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            // Biztonsági okokból azonos üzenetet adunk vissza
            return response()->json([
                'success' => true,
                'message' => 'Ha létezik fiók ezzel az email címmel, linket küldtünk.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ha létezik fiók ezzel az email címmel, linket küldtünk.',
        ]);
    }
}
