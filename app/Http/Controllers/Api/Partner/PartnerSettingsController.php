<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\UpdateGlobalSettingsRequest;
use App\Http\Requests\Api\Partner\UpdateProjectSettingsRequest;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;

/**
 * Partner Settings Controller - Globális és projekt-szintű beállítások.
 */
class PartnerSettingsController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Globális partner beállítások lekérése.
     */
    public function getGlobalSettings(): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        return response()->json([
            'data' => [
                'default_max_retouch_photos' => $partner->default_max_retouch_photos ?? 3,
                'default_gallery_deadline_days' => $partner->default_gallery_deadline_days ?? 14,
                'default_free_edit_window_hours' => $partner->default_free_edit_window_hours ?? 24,
                'billing_enabled' => (bool) $partner->billing_enabled,
            ],
        ]);
    }

    /**
     * Globális partner beállítások mentése.
     */
    public function updateGlobalSettings(UpdateGlobalSettingsRequest $request): JsonResponse
    {
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        $updateData = [
            'default_max_retouch_photos' => $request->validated('default_max_retouch_photos'),
        ];

        if ($request->has('default_gallery_deadline_days')) {
            $updateData['default_gallery_deadline_days'] = $request->validated('default_gallery_deadline_days');
        }

        if ($request->has('default_free_edit_window_hours')) {
            $updateData['default_free_edit_window_hours'] = $request->validated('default_free_edit_window_hours');
        }

        if ($request->has('billing_enabled')) {
            $updateData['billing_enabled'] = $request->validated('billing_enabled');
        }

        $partner->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Beállítások sikeresen mentve',
            'data' => [
                'default_max_retouch_photos' => $partner->default_max_retouch_photos,
                'default_gallery_deadline_days' => $partner->default_gallery_deadline_days ?? 14,
                'default_free_edit_window_hours' => $partner->default_free_edit_window_hours ?? 24,
                'billing_enabled' => (bool) $partner->billing_enabled,
            ],
        ]);
    }

    /**
     * Projekt beállítások lekérése (override értékek + globális fallback).
     */
    public function getProjectSettings(int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);
        $partner = TabloPartner::findOrFail($this->getPartnerIdOrFail());

        $globalDefault = $partner->default_max_retouch_photos ?? 3;

        return response()->json([
            'data' => [
                'max_retouch_photos' => $project->max_retouch_photos,
                'effective_max_retouch_photos' => $project->getEffectiveMaxRetouchPhotos(),
                'global_default_max_retouch_photos' => $globalDefault,
                'free_edit_window_hours' => $project->free_edit_window_hours,
                'effective_free_edit_window_hours' => $project->getEffectiveFreeEditWindowHours(),
                'global_default_free_edit_window_hours' => $partner->default_free_edit_window_hours ?? 24,
            ],
        ]);
    }

    /**
     * Projekt beállítások mentése (override).
     */
    public function updateProjectSettings(UpdateProjectSettingsRequest $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $updateData = [
            'max_retouch_photos' => $request->validated('max_retouch_photos'),
        ];

        if ($request->has('free_edit_window_hours')) {
            $updateData['free_edit_window_hours'] = $request->validated('free_edit_window_hours');
        }

        $project->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Projekt beállítások sikeresen mentve',
            'data' => [
                'max_retouch_photos' => $project->max_retouch_photos,
                'effective_max_retouch_photos' => $project->getEffectiveMaxRetouchPhotos(),
                'free_edit_window_hours' => $project->free_edit_window_hours,
                'effective_free_edit_window_hours' => $project->getEffectiveFreeEditWindowHours(),
            ],
        ]);
    }
}
