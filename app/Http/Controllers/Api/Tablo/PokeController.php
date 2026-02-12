<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Requests\Api\Tablo\PokeReactionRequest;
use App\Http\Requests\Api\Tablo\SendPokeRequest;
use App\Models\TabloPoke;
use App\Services\Tablo\PokeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Poke Controller
 *
 * Peer-to-peer bökés rendszer API végpontjai.
 */
class PokeController extends BaseTabloController
{
    public function __construct(
        protected PokeService $pokeService
    ) {}

    /**
     * Preset üzenetek lekérése
     *
     * GET /api/tablo-frontend/pokes/presets
     */
    public function presets(Request $request): JsonResponse
    {
        $category = $request->query('category');

        $presets = $this->pokeService->getPresets($category);

        return $this->successResponse([
            'presets' => $presets->map->toApiResponse()->toArray(),
        ]);
    }

    /**
     * Bökés küldése
     *
     * POST /api/tablo-frontend/pokes
     */
    public function store(SendPokeRequest $request): JsonResponse
    {
        // Guest session szükséges
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $fromSession = $result;

        $validated = $request->validated();

        // Célpont lekérése
        $project = $this->getProject($request);
        $targetSession = $project->guestSessions()->find($validated['target_id']);

        if (! $targetSession) {
            return $this->notFoundResponse('A célpont nem található ebben a projektben.');
        }

        try {
            $poke = $this->pokeService->sendPoke(
                fromSession: $fromSession,
                targetSession: $targetSession,
                category: $validated['category'] ?? TabloPoke::CATEGORY_GENERAL,
                presetKey: $validated['preset_key'] ?? null,
                customMessage: $validated['custom_message'] ?? null
            );

            return $this->successResponse([
                'poke' => $poke->toApiResponse(),
                'daily_limit' => $this->pokeService->getDailyLimitInfo($fromSession),
            ], 'Bökés sikeresen elküldve!', 201);
        } catch (\InvalidArgumentException $e) {
            // Business logic validation - safe to expose
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Küldött bökések
     *
     * GET /api/tablo-frontend/pokes/sent
     */
    public function sent(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        $pokes = $this->pokeService->getSentPokes($session);

        return $this->successResponse([
            'pokes' => $pokes->map->toApiResponse()->toArray(),
            'daily_limit' => $this->pokeService->getDailyLimitInfo($session),
        ]);
    }

    /**
     * Kapott bökések
     *
     * GET /api/tablo-frontend/pokes/received
     */
    public function received(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        $pokes = $this->pokeService->getReceivedPokes($session);

        return $this->successResponse([
            'pokes' => $pokes->map->toApiResponse()->toArray(),
            'unread_count' => $this->pokeService->getUnreadCount($session),
        ]);
    }

    /**
     * Reakció hozzáadása
     *
     * POST /api/tablo-frontend/pokes/{id}/reaction
     */
    public function reaction(PokeReactionRequest $request, int $id): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        $validated = $request->validated();

        // Bökés keresése
        $poke = TabloPoke::receivedBy($session->id)->find($id);

        if (! $poke) {
            return $this->notFoundResponse('A bökés nem található.');
        }

        try {
            $poke = $this->pokeService->addReaction($poke, $validated['reaction']);

            return $this->successResponse([
                'poke' => $poke->toApiResponse(),
            ], 'Reakció hozzáadva!');
        } catch (\InvalidArgumentException $e) {
            // Business logic validation - safe to expose
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Olvasottnak jelölés
     *
     * POST /api/tablo-frontend/pokes/{id}/read
     */
    public function read(Request $request, int $id): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        $poke = TabloPoke::receivedBy($session->id)->find($id);

        if (! $poke) {
            return $this->notFoundResponse('A bökés nem található.');
        }

        $this->pokeService->markAsRead($poke);

        return $this->successResponse([
            'poke' => $poke->fresh()->toApiResponse(),
        ]);
    }

    /**
     * Összes olvasottnak jelölése
     *
     * POST /api/tablo-frontend/pokes/read-all
     */
    public function readAll(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        $count = $this->pokeService->markAllAsRead($session);

        return $this->successResponse([
            'marked_count' => $count,
        ], "{$count} bökés olvasottnak jelölve.");
    }

    /**
     * Olvasatlan bökések száma
     *
     * GET /api/tablo-frontend/pokes/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        return $this->successResponse([
            'unread_count' => $this->pokeService->getUnreadCount($session),
        ]);
    }

    /**
     * Napi limit információ
     *
     * GET /api/tablo-frontend/pokes/daily-limit
     */
    public function dailyLimit(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        return $this->successResponse(
            $this->pokeService->getDailyLimitInfo($session)
        );
    }

    /**
     * Ellenőrzés: bökhető-e a célpont
     *
     * GET /api/tablo-frontend/pokes/can-poke/{targetId}
     */
    public function canPoke(Request $request, int $targetId): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $fromSession = $result;

        $project = $this->getProject($request);
        $targetSession = $project->guestSessions()->find($targetId);

        if (! $targetSession) {
            return $this->notFoundResponse('A célpont nem található.');
        }

        $status = $this->pokeService->getPokeStatus($fromSession, $targetSession);

        return $this->successResponse($status);
    }
}
