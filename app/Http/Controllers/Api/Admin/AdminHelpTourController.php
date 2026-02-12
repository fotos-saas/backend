<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreHelpTourRequest;
use App\Http\Requests\Api\Admin\UpdateHelpTourRequest;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AdminHelpTourController extends Controller
{
    public function index(): JsonResponse
    {
        $tours = HelpTour::withCount('steps')
            ->orderBy('title')
            ->get();

        return $this->successResponse($tours);
    }

    public function store(StoreHelpTourRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $steps = $validated['steps'] ?? [];
        unset($validated['steps']);

        $tour = HelpTour::create($validated);

        foreach ($steps as $index => $stepData) {
            $stepData['help_tour_id'] = $tour->id;
            $stepData['step_number'] = $index + 1;
            HelpTourStep::create($stepData);
        }

        $tour->load('steps');

        return $this->createdResponse($tour, 'Túra létrehozva');
    }

    public function show(HelpTour $tour): JsonResponse
    {
        $tour->load('steps');

        return $this->successResponse($tour);
    }

    public function update(UpdateHelpTourRequest $request, HelpTour $tour): JsonResponse
    {
        $validated = $request->validated();

        $steps = $validated['steps'] ?? null;
        unset($validated['steps']);

        $tour->update($validated);

        if ($steps !== null) {
            $tour->steps()->delete();
            foreach ($steps as $index => $stepData) {
                $stepData['help_tour_id'] = $tour->id;
                $stepData['step_number'] = $index + 1;
                HelpTourStep::create($stepData);
            }
        }

        $tour->load('steps');

        return $this->successResponse($tour, 'Túra frissítve');
    }

    public function destroy(HelpTour $tour): JsonResponse
    {
        $tour->delete();

        return $this->successMessageResponse('Túra törölve');
    }
}
