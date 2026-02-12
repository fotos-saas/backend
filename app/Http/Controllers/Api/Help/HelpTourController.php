<?php

namespace App\Http\Controllers\Api\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Help\UpdateTourProgressRequest;
use App\Models\HelpTour;
use App\Services\Help\HelpTourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpTourController extends Controller
{
    public function __construct(
        private HelpTourService $tourService,
    ) {}

    /**
     * Elérhető túrák az aktuális route-ra.
     */
    public function index(Request $request): JsonResponse
    {
        $route = $request->query('route', '');
        $role = $request->query('role', 'guest');
        $user = $request->user();

        $tours = $this->tourService->getAvailableTours($route, $role, $user->id);

        return $this->successResponse($tours->values());
    }

    /**
     * Egyetlen túra részletei.
     */
    public function show(HelpTour $tour): JsonResponse
    {
        $tour->load('steps');

        return $this->successResponse($tour);
    }

    /**
     * Túra haladás frissítése.
     */
    public function updateProgress(UpdateTourProgressRequest $request, HelpTour $tour): JsonResponse
    {
        $validated = $request->validated();

        $progress = $this->tourService->updateProgress(
            $request->user()->id,
            $tour->id,
            $validated['status'],
            $validated['step_number'],
        );

        return $this->successResponse($progress);
    }

    /**
     * User összes túra haladása.
     */
    public function allProgress(Request $request): JsonResponse
    {
        $progress = $this->tourService->getUserProgress($request->user()->id);

        return $this->successResponse($progress);
    }
}
