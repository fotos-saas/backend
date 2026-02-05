<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Enums\TabloProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Project\StoreTabloProjectRequest;
use App\Http\Requests\Api\Tablo\Project\SyncStatusRequest;
use App\Http\Requests\Api\Tablo\Project\UpdateStatusRequest;
use App\Http\Requests\Api\Tablo\Project\UpdateTabloProjectRequest;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TabloProjectController extends Controller
{
    /**
     * List all projects with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = TabloProject::with(['partner', 'contacts', 'missingPersons']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('partner_id')) {
            $query->where('partner_id', $request->input('partner_id'));
        }

        if ($request->has('local_id')) {
            $query->where('local_id', $request->input('local_id'));
        }

        if ($request->has('external_id')) {
            $query->where('external_id', $request->input('external_id'));
        }

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
    public function store(StoreTabloProjectRequest $request): JsonResponse
    {
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
    public function update(UpdateTabloProjectRequest $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $project->update($request->only([
            'name', 'partner_id', 'local_id', 'external_id', 'status', 'is_aware',
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
    public function updateStatus(UpdateStatusRequest $request, int $id): JsonResponse
    {
        $project = TabloProject::find($id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
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
     * Sync status from legacy system
     */
    public function syncStatus(SyncStatusRequest $request): JsonResponse
    {
        $fotocmsId = $request->input('fotocms_id');
        $externalId = $request->input('project_id');
        $legacyStatusId = (int) $request->input('status_id');

        if (! $fotocmsId && ! $externalId) {
            return response()->json([
                'success' => false,
                'message' => 'fotocms_id vagy project_id megadása kötelező',
            ], 422);
        }

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
