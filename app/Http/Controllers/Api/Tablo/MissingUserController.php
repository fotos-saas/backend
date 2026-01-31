<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Services\Tablo\MissingUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Missing User Controller
 *
 * Hiányzó felhasználók lekérdezése bökés rendszerhez.
 */
class MissingUserController extends BaseTabloController
{
    public function __construct(
        protected MissingUserService $missingUserService
    ) {}

    /**
     * Összes hiányzó felhasználó kategóriánként
     *
     * GET /api/tablo-frontend/missing
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        // Guest session (opcionális - bökés státusz miatt)
        $currentSession = $this->getGuestSession($request);

        $missingUsers = $this->missingUserService->getMissingUsers($project, $currentSession);

        return $this->successResponse([
            'categories' => $missingUsers,
            'summary' => $this->missingUserService->getMissingSummary($project),
        ]);
    }

    /**
     * Szavazásból hiányzók
     *
     * GET /api/tablo-frontend/missing/voting
     */
    public function voting(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $currentSession = $this->getGuestSession($request);

        return $this->successResponse(
            $this->missingUserService->getMissingForVoting($project, $currentSession)
        );
    }

    /**
     * Fotózásból hiányzók
     *
     * GET /api/tablo-frontend/missing/photoshoot
     */
    public function photoshoot(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $currentSession = $this->getGuestSession($request);

        return $this->successResponse(
            $this->missingUserService->getMissingForPhotoshoot($project, $currentSession)
        );
    }

    /**
     * Képválasztásból hiányzók
     *
     * GET /api/tablo-frontend/missing/image-selection
     */
    public function imageSelection(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $currentSession = $this->getGuestSession($request);

        return $this->successResponse(
            $this->missingUserService->getMissingForImageSelection($project, $currentSession)
        );
    }

    /**
     * Saját hiányzási státusz
     *
     * GET /api/tablo-frontend/missing/my-status
     */
    public function myStatus(Request $request): JsonResponse
    {
        $result = $this->requireActiveGuestSession($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $session = $result;

        return $this->successResponse(
            $this->missingUserService->getUserMissingStatus($session)
        );
    }
}
