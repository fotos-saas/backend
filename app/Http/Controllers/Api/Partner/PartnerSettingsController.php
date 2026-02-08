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
                'default_zip_content' => $partner->default_zip_content ?? 'all',
                'default_file_naming' => $partner->default_file_naming ?? 'original',
                'export_always_ask' => $partner->export_always_ask ?? true,
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

        if ($request->has('default_zip_content')) {
            $updateData['default_zip_content'] = $request->validated('default_zip_content');
        }

        if ($request->has('default_file_naming')) {
            $updateData['default_file_naming'] = $request->validated('default_file_naming');
        }

        if ($request->has('export_always_ask')) {
            $updateData['export_always_ask'] = $request->validated('export_always_ask');
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
                'default_zip_content' => $partner->default_zip_content ?? 'all',
                'default_file_naming' => $partner->default_file_naming ?? 'original',
                'export_always_ask' => $partner->export_always_ask ?? true,
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

        $effectiveExport = $project->getEffectiveExportSettings();

        return response()->json([
            'data' => [
                'max_retouch_photos' => $project->max_retouch_photos,
                'effective_max_retouch_photos' => $project->getEffectiveMaxRetouchPhotos(),
                'global_default_max_retouch_photos' => $globalDefault,
                'free_edit_window_hours' => $project->free_edit_window_hours,
                'effective_free_edit_window_hours' => $project->getEffectiveFreeEditWindowHours(),
                'global_default_free_edit_window_hours' => $partner->default_free_edit_window_hours ?? 24,
                'export_zip_content' => $project->export_zip_content,
                'export_file_naming' => $project->export_file_naming,
                'export_always_ask' => $project->export_always_ask,
                'effective_export' => $effectiveExport,
                'global_default_zip_content' => $partner->default_zip_content ?? 'all',
                'global_default_file_naming' => $partner->default_file_naming ?? 'original',
                'global_export_always_ask' => $partner->export_always_ask ?? true,
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

        if ($request->has('export_zip_content')) {
            $updateData['export_zip_content'] = $request->validated('export_zip_content');
        }

        if ($request->has('export_file_naming')) {
            $updateData['export_file_naming'] = $request->validated('export_file_naming');
        }

        if ($request->has('export_always_ask')) {
            $updateData['export_always_ask'] = $request->validated('export_always_ask');
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
                'effective_export' => $project->getEffectiveExportSettings(),
            ],
        ]);
    }
}
