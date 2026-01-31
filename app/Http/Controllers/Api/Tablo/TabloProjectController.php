<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Enums\TabloProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TabloProjectController extends Controller
{
    /**
     * List all projects with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = TabloProject::with(['partner', 'contacts', 'missingPersons']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by partner
        if ($request->has('partner_id')) {
            $query->where('partner_id', $request->input('partner_id'));
        }

        // Filter by local_id
        if ($request->has('local_id')) {
            $query->where('local_id', $request->input('local_id'));
        }

        // Filter by external_id
        if ($request->has('external_id')) {
            $query->where('external_id', $request->input('external_id'));
        }

        // Filter by is_aware
        if ($request->has('is_aware')) {
            $query->where('is_aware', filter_var($request->input('is_aware'), FILTER_VALIDATE_BOOLEAN));
        }

        $projects = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $projects->map(fn ($p) => $this->transformProject($p)),
        ]);
    }

    /**
     * Get single project
     */
    public function show(int $id): JsonResponse
    {
        $project = TabloProject::with(['partner', 'contacts', 'missingPersons', 'notes.user'])
            ->find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformProject($project, true),
        ]);
    }

    /**
     * Create new project
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'partner_id' => 'required|exists:tablo_partners,id',
            'local_id' => 'nullable|string|max:255|unique:tablo_projects,local_id',
            'external_id' => 'nullable|string|max:255|unique:tablo_projects,external_id',
            'status' => 'nullable|in:'.implode(',', TabloProjectStatus::values()),
            'is_aware' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = TabloProject::create([
            'name' => $request->input('name'),
            'partner_id' => $request->input('partner_id'),
            'local_id' => $request->input('local_id'),
            'external_id' => $request->input('external_id'),
            'status' => $request->input('status', TabloProjectStatus::NotStarted->value),
            'is_aware' => $request->input('is_aware', false),
        ]);

        $project->load(['partner', 'contacts', 'missingPersons']);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen létrehozva',
            'data' => $this->transformProject($project),
        ], 201);
    }

    /**
     * Update project
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'partner_id' => 'sometimes|exists:tablo_partners,id',
            'local_id' => 'nullable|string|max:255|unique:tablo_projects,local_id,'.$id,
            'external_id' => 'nullable|string|max:255|unique:tablo_projects,external_id,'.$id,
            'status' => 'nullable|in:'.implode(',', TabloProjectStatus::values()),
            'is_aware' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project->update($request->only([
            'name',
            'partner_id',
            'local_id',
            'external_id',
            'status',
            'is_aware',
        ]));

        $project->load(['partner', 'contacts', 'missingPersons']);

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen frissítve',
            'data' => $this->transformProject($project),
        ]);
    }

    /**
     * Update only status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:'.implode(',', TabloProjectStatus::values()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project->update(['status' => $request->input('status')]);

        return response()->json([
            'success' => true,
            'message' => 'Státusz sikeresen frissítve',
            'data' => [
                'id' => $project->id,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
            ],
        ]);
    }

    /**
     * Sync status from legacy system using fotocms_id or external_id and legacy status_id
     * POST /api/tablo/projects/sync-status
     * Body: { "fotocms_id": 728, "status_id": 3 } OR { "project_id": 94, "status_id": 3 }
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fotocms_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'status_id' => 'required|integer|min:1|max:13',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prefer fotocms_id, fallback to project_id (external_id)
        $fotocmsId = $request->input('fotocms_id');
        $externalId = $request->input('project_id');
        $legacyStatusId = (int) $request->input('status_id');

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

        // Convert legacy status_id to enum
        $status = TabloProjectStatus::fromLegacyId($legacyStatusId);

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => "Érvénytelen státusz ID: {$legacyStatusId}",
            ], 422);
        }

        $project->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'message' => 'Státusz sikeresen szinkronizálva',
            'data' => [
                'id' => $project->id,
                'fotocms_id' => $project->fotocms_id,
                'external_id' => $project->external_id,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'legacy_status_id' => $legacyStatusId,
            ],
        ]);
    }

    /**
     * Delete project
     */
    public function destroy(int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projekt sikeresen törölve',
        ]);
    }

    /**
     * Upload samples to a project
     * POST /api/tablo-management/projects/{id}/samples
     * Body: multipart form-data with 'samples[]' files
     */
    public function uploadSamples(Request $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'samples' => 'required|array|min:1',
            'samples.*' => 'required|image|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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
    public function syncSamples(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fotocms_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'samples' => 'required|array',
            'samples.*.url' => 'required|url',
            'samples.*.name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
    public function updateSample(Request $request, int $projectId, int $mediaId): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $media->setCustomProperty('is_active', $request->input('is_active'));
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

    /**
     * Transform project for API response
     */
    private function transformProject(TabloProject $project, bool $includeNotes = false): array
    {
        $data = [
            'id' => $project->id,
            'fotocms_id' => $project->fotocms_id,
            'local_id' => $project->local_id,
            'external_id' => $project->external_id,
            'name' => $project->name,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'is_aware' => $project->is_aware,
            'partner' => $project->partner ? [
                'id' => $project->partner->id,
                'name' => $project->partner->name,
                'slug' => $project->partner->slug,
                'local_id' => $project->partner->local_id,
            ] : null,
            'contacts' => $project->contacts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'note' => $c->note,
            ])->toArray(),
            'missing_persons' => $project->missingPersons->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'local_id' => $m->local_id,
                'note' => $m->note,
            ])->toArray(),
            'contacts_count' => $project->contacts->count(),
            'missing_persons_count' => $project->missingPersons->count(),
            'created_at' => $project->created_at->toIso8601String(),
            'updated_at' => $project->updated_at->toIso8601String(),
        ];

        if ($includeNotes && $project->relationLoaded('notes')) {
            $data['notes'] = $project->notes->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'author' => $n->user?->name ?? 'Ismeretlen',
                'created_at' => $n->created_at->toIso8601String(),
            ])->toArray();
        }

        return $data;
    }
}
