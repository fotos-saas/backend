<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpTour;
use App\Models\HelpTourStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:help_tours,key',
            'title' => 'required|string|max:255',
            'trigger_route' => 'required|string|max:255',
            'target_roles' => 'array',
            'target_roles.*' => 'string',
            'target_plans' => 'array',
            'target_plans.*' => 'string',
            'trigger_type' => 'string|in:first_visit,manual,always',
            'is_active' => 'boolean',
            'steps' => 'array',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.content' => 'required|string',
            'steps.*.target_selector' => 'nullable|string|max:255',
            'steps.*.placement' => 'string|in:top,bottom,left,right',
            'steps.*.highlight_type' => 'string|in:spotlight,border,none',
            'steps.*.allow_skip' => 'boolean',
        ]);

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

    public function update(Request $request, HelpTour $tour): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'trigger_route' => 'string|max:255',
            'target_roles' => 'array',
            'target_plans' => 'array',
            'trigger_type' => 'string|in:first_visit,manual,always',
            'is_active' => 'boolean',
            'steps' => 'array',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.content' => 'required|string',
            'steps.*.target_selector' => 'nullable|string|max:255',
            'steps.*.placement' => 'string|in:top,bottom,left,right',
            'steps.*.highlight_type' => 'string|in:spotlight,border,none',
            'steps.*.allow_skip' => 'boolean',
        ]);

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
