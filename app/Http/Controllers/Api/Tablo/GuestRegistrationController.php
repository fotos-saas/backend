<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Actions\Tablo\RequestRestoreLinkAction;
use App\Actions\Tablo\SearchParticipantsAction;
use App\Actions\Tablo\SendGuestDeviceLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Guest\RegisterGuestRequest;
use App\Http\Requests\Api\Tablo\Guest\RegisterWithIdentificationRequest;
use App\Http\Requests\Api\Tablo\Guest\RequestRestoreLinkRequest;
use App\Http\Requests\Api\Tablo\Guest\SendGuestLinkRequest;
use App\Http\Requests\Api\Tablo\Guest\SessionTokenRequest;
use App\Http\Requests\Api\Tablo\Guest\UpdateGuestRequest;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Services\Tablo\GuestSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendég regisztráció, azonosítás és session kezelés.
 * Token-ból azonosítja a projektet.
 */
class GuestRegistrationController extends Controller
{
    public function __construct(
        protected GuestSessionService $guestSessionService
    ) {}

    /** POST /api/tablo-frontend/guest/register */
    public function register(RegisterGuestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = $this->resolveProject($request);
        if (! $project) {
            return $this->projectNotFound();
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

    /** POST /api/tablo-frontend/guest/register-with-identification */
    public function registerWithIdentification(RegisterWithIdentificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $project = $this->resolveProject($request);
        if (! $project) {
            return $this->projectNotFound();
        }

        // Person validáció: a projekt persons listájában van-e
        if ($validated['person_id'] ?? null) {
            if (! $project->persons()->where('id', $validated['person_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A kiválasztott személy nem tartozik ehhez a projekthez.',
                ], 422);
            }
        }

        $result = $this->guestSessionService->registerWithIdentification(
            $project,
            $validated['nickname'],
            $validated['person_id'] ?? null,
            $validated['email'] ?? null,
            $validated['device_identifier'] ?? null,
            $request->ip()
        );

        $session = $result['session'];
        return response()->json([
            'success' => true,
            'message' => $result['has_conflict'] ? $result['conflict_message'] : 'Sikeres regisztráció!',
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
                'verification_status' => $session->verification_status,
                'is_pending' => $session->isPending(),
                'person_id' => $session->tablo_person_id,
                'person_name' => $session->person?->name,
            ],
            'has_conflict' => $result['has_conflict'],
        ]);
    }

    /** POST /api/tablo-frontend/guest/validate */
    public function validate(SessionTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $session = $this->guestSessionService->validate(
            $validated['session_token'],
            $this->resolveProjectId($request)
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

    /** PUT /api/tablo-frontend/guest/update */
    public function update(UpdateGuestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $session = $this->resolveSession($request, $validated['session_token']);
        if (! $session) {
            return $this->sessionNotFound();
        }

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

    /** POST /api/tablo-frontend/guest/send-link */
    public function sendLink(SendGuestLinkRequest $request, SendGuestDeviceLinkAction $action): JsonResponse
    {
        $validated = $request->validated();
        $project = $this->resolveProject($request);
        if (! $project) {
            return $this->projectNotFound();
        }

        $result = $action->execute($project, $validated['session_token'], $validated['email']);
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'link' => $result['link'],
        ], $result['status']);
    }

    /** POST /api/tablo-frontend/guest/request-restore-link */
    public function requestRestoreLink(RequestRestoreLinkRequest $request, RequestRestoreLinkAction $action): JsonResponse
    {
        $validated = $request->validated();
        $result = $action->execute($this->resolveProjectId($request), $validated['email']);
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
    }

    /** POST /api/tablo-frontend/guest/heartbeat */
    public function heartbeat(SessionTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $session = $this->resolveSession($request, $validated['session_token']);
        if (! $session) {
            return $this->sessionNotFound();
        }

        $this->guestSessionService->heartbeat($session, $request->ip());
        return response()->json(['success' => true]);
    }

    /** GET /api/tablo-frontend/guest/session-status */
    public function sessionStatus(SessionTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $session = $this->resolveSession($request, $validated['session_token']);
        if (! $session) {
            return response()->json([
                'valid' => false,
                'reason' => 'deleted',
                'message' => 'A munkamenet törölve lett.',
            ], 401);
        }
        if ($session->is_banned) {
            return response()->json([
                'valid' => false,
                'reason' => 'banned',
                'message' => 'Hozzáférés megtagadva. Kérlek vedd fel a kapcsolatot a szervezőkkel.',
            ], 403);
        }

        return response()->json(['valid' => true]);
    }

    /** GET /api/tablo-frontend/guest/verification-status */
    public function checkVerificationStatus(SessionTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $session = $this->resolveSession($request, $validated['session_token']);
        if (! $session) {
            return $this->sessionNotFound();
        }

        return response()->json([
            'success' => true,
            'data' => $this->guestSessionService->checkVerificationStatus($session),
        ]);
    }

    /** GET /api/tablo-frontend/guest/missing-persons/search */
    public function searchPersons(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);
        if (! $project) {
            return $this->projectNotFound();
        }

        return response()->json([
            'success' => true,
            'data' => $this->guestSessionService->searchPersons(
                $project,
                $request->get('q', ''),
                min($request->integer('limit', 10), 20)
            ),
        ]);
    }

    /** GET /api/tablo-frontend/participants/search */
    public function searchParticipants(Request $request, SearchParticipantsAction $action): JsonResponse
    {
        $project = $this->resolveProject($request);
        if (! $project) {
            return $this->projectNotFound();
        }

        return response()->json([
            'success' => true,
            'data' => $action->execute(
                $project,
                $request->get('q', ''),
                min($request->integer('limit', 10), 20)
            ),
        ]);
    }

    private function resolveProject(Request $request): ?TabloProject
    {
        return TabloProject::find($this->resolveProjectId($request));
    }

    private function resolveProjectId(Request $request): int
    {
        return $request->user()->currentAccessToken()->tablo_project_id;
    }

    private function resolveSession(Request $request, string $sessionToken): ?TabloGuestSession
    {
        return TabloGuestSession::where('session_token', $sessionToken)
            ->where('tablo_project_id', $this->resolveProjectId($request))
            ->first();
    }

    private function projectNotFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Projekt nem található'], 404);
    }

    private function sessionNotFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Session nem található'], 404);
    }
}
