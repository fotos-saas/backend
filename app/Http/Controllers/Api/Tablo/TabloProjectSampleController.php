<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Sample\SyncSamplesRequest;
use App\Http\Requests\Api\Tablo\Sample\UpdateSampleRequest;
use App\Http\Requests\Api\Tablo\Sample\UploadSamplesRequest;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TabloProjectSampleController extends Controller
{
    /**
     * Upload samples to a project
     * POST /api/tablo-management/projects/{id}/samples
     * Body: multipart form-data with 'samples[]' files
     */
    public function uploadSamples(UploadSamplesRequest $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $uploaded = [];

        foreach ($request->file('samples') as $file) {
            $media = $project->addMedia($file)
                ->preservingOriginal()
                ->withCustomProperties(['is_active' => true])
                ->toMediaCollection('samples');

            $uploaded[] = [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'is_active' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($uploaded) . ' minta sikeresen feltöltve',
            'data' => $uploaded,
        ], 201);
    }

    /**
     * Sync samples from external URL
     * POST /api/tablo-management/projects/sync-samples
     * Body: { "fotocms_id": 728, "samples": [...] } OR { "project_id": 94, "samples": [...] }
     */
    public function syncSamples(SyncSamplesRequest $request): JsonResponse
    {
        // Prefer fotocms_id, fallback to project_id (external_id)
        $fotocmsId = $request->input('fotocms_id');
        $externalId = $request->input('project_id');

        if (! $fotocmsId && ! $externalId) {
            return response()->json([
                'success' => false,
                'message' => 'fotocms_id vagy project_id megadása kötelező',
            ], 422);
        }

        // Find project by fotocms_id or external_id
        $project = null;
        if ($fotocmsId) {
            $project = TabloProject::where('fotocms_id', $fotocmsId)->first();
        }
        if (! $project && $externalId) {
            $project = TabloProject::where('external_id', (string) $externalId)->first();
        }

        if (! $project) {
            $idInfo = $fotocmsId ? "fotocms_id={$fotocmsId}" : "external_id={$externalId}";
            return response()->json([
                'success' => false,
                'message' => "Projekt nem található {$idInfo}",
            ], 404);
        }

        $uploaded = [];
        $errors = [];

        foreach ($request->input('samples') as $sample) {
            try {
                $media = $project->addMediaFromUrl($sample['url'])
                    ->usingName($sample['name'] ?? null)
                    ->withCustomProperties(['is_active' => true])
                    ->toMediaCollection('samples');

                $uploaded[] = [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                    'is_active' => true,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'url' => $sample['url'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($uploaded) . ' minta sikeresen feltöltve' . (count($errors) > 0 ? ', ' . count($errors) . ' hiba' : ''),
            'data' => [
                'project_id' => $project->id,
                'fotocms_id' => $project->fotocms_id,
                'external_id' => $project->external_id,
                'uploaded' => $uploaded,
                'errors' => $errors,
                'total_samples' => $project->getMedia('samples')->count(),
            ],
        ]);
    }

    /**
     * Get samples for a project
     * GET /api/tablo-management/projects/{id}/samples
     * Query params: ?active_only=true (optional, default: false - returns all)
     */
    public function getSamples(Request $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $samples = $project->getMedia('samples');

        // Ha active_only=true, csak az aktív mintákat adjuk vissza
        if (filter_var($request->query('active_only'), FILTER_VALIDATE_BOOLEAN)) {
            $samples = $samples->filter(fn ($media) => $media->getCustomProperty('is_active', true));
        }

        $samples = $samples->map(fn ($media) => [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'size' => $media->size,
            'human_readable_size' => $media->human_readable_size,
            'url' => $media->getUrl(),
            'thumb_url' => $media->getUrl('thumb'),
            'is_active' => $media->getCustomProperty('is_active', true),
            'created_at' => $media->created_at->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $samples,
        ]);
    }

    /**
     * Update sample status (is_active)
     * PATCH /api/tablo-management/projects/{projectId}/samples/{mediaId}
     * Body: { "is_active": true/false }
     */
    public function updateSample(UpdateSampleRequest $request, int $projectId, int $mediaId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $media = $project->getMedia('samples')->firstWhere('id', $mediaId);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => 'Minta nem található',
            ], 404);
        }

        $media->setCustomProperty('is_active', $request->validated('is_active'));
        $media->save();

        return response()->json([
            'success' => true,
            'message' => 'Minta státusz frissítve',
            'data' => [
                'id' => $media->id,
                'is_active' => $media->getCustomProperty('is_active'),
            ],
        ]);
    }

    /**
     * Delete a sample
     * DELETE /api/tablo-management/projects/{projectId}/samples/{mediaId}
     */
    public function deleteSample(int $projectId, int $mediaId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $media = $project->getMedia('samples')->firstWhere('id', $mediaId);

        if (! $media) {
            return response()->json([
                'success' => false,
                'message' => 'Minta nem található',
            ], 404);
        }

        $media->delete();

        return response()->json([
            'success' => true,
            'message' => 'Minta sikeresen törölve',
        ]);
    }
}
