<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TabloSampleController extends Controller
{
    /**
     * Get samples (minta képek) for a tablo project.
     * Legújabb elől rendezve, dátummal.
     */
    public function getSamples(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $tabloProject = TabloProject::find($projectId);

        if (! $tabloProject) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        // Get samples from media collection, ordered by newest first
        // Only return active samples
        $samples = $tabloProject->getMedia('samples')
            ->filter(fn ($media) => $media->getCustomProperty('is_active', true))
            ->sortByDesc('created_at')
            ->map(function ($media) {
                // Convert full URL to relative path for Angular proxy compatibility
                $url = $media->getUrl();
                $thumbUrl = $media->getUrl('thumb');

                // Extract path from full URL (remove http://localhost:8000 or similar)
                $urlPath = parse_url($url, PHP_URL_PATH);
                $thumbPath = parse_url($thumbUrl, PHP_URL_PATH);

                return [
                    'id' => $media->id,
                    'fileName' => $media->file_name,
                    'url' => $urlPath,
                    'thumbUrl' => $thumbPath,
                    'description' => $media->getCustomProperty('description'),
                    'createdAt' => $media->created_at->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $samples,
            'totalCount' => $samples->count(),
        ]);
    }
}
