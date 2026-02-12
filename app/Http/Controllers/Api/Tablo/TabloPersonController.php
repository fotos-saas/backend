<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Actions\Tablo\BatchStorePersonsAction;
use App\Actions\Tablo\SyncPersonsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\BatchDestroyPersonRequest;
use App\Http\Requests\Api\Tablo\BatchStorePersonRequest;
use App\Http\Requests\Api\Tablo\ExportPersonsRequest;
use App\Http\Requests\Api\Tablo\StorePersonRequest;
use App\Http\Requests\Api\Tablo\SyncPersonsRequest;
use App\Http\Requests\Api\Tablo\UpdatePersonRequest;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TabloPersonController extends Controller
{
    /**
     * List persons for a project
     */
    public function index(int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $persons = $project->persons()
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $persons->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'local_id' => $m->local_id,
                'note' => $m->note,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Add person to project
     */
    public function store(StorePersonRequest $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $person = $project->persons()->create([
            'name' => $request->input('name'),
            'local_id' => $request->input('local_id'),
            'note' => $request->input('note'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Személy sikeresen hozzáadva',
            'data' => [
                'id' => $person->id,
                'name' => $person->name,
                'local_id' => $person->local_id,
                'note' => $person->note,
                'created_at' => $person->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Batch add persons
     */
    public function batchStore(BatchStorePersonRequest $request, int $projectId, BatchStorePersonsAction $action): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $created = $action->execute($project, $request->input('persons'));

        return response()->json([
            'success' => true,
            'message' => count($created).' személy sikeresen hozzáadva',
            'data' => $created,
        ], 201);
    }

    /**
     * Update person
     */
    public function update(UpdatePersonRequest $request, int $id): JsonResponse
    {
        $person = TabloPerson::find($id);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'Személy nem található',
            ], 404);
        }

        $person->update($request->only(['name', 'local_id', 'note']));

        return response()->json([
            'success' => true,
            'message' => 'Személy sikeresen frissítve',
            'data' => [
                'id' => $person->id,
                'name' => $person->name,
                'local_id' => $person->local_id,
                'note' => $person->note,
            ],
        ]);
    }

    /**
     * Delete person
     */
    public function destroy(int $id): JsonResponse
    {
        $person = TabloPerson::find($id);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'Személy nem található',
            ], 404);
        }

        $person->delete();

        return response()->json([
            'success' => true,
            'message' => 'Személy sikeresen törölve',
        ]);
    }

    /**
     * Sync persons from legacy system
     * POST /api/tablo-management/projects/sync-persons
     * Body: { "project_id": 94, "persons": [{"name": "Kiss Péter"}, {"name": "Nagy Anna", "local_id": "123"}] }
     */
    public function syncPersons(SyncPersonsRequest $request, SyncPersonsAction $action): JsonResponse
    {
        $externalId = (string) $request->input('project_id');
        $project = TabloProject::where('external_id', $externalId)->first();

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => "Projekt nem található external_id={$externalId}",
            ], 404);
        }

        $result = $action->execute($project, collect($request->input('persons')));

        return response()->json([
            'success' => true,
            'message' => "Szinkronizálás kész: +{$result['added']} hozzáadva, ~{$result['updated']} frissítve, -{$result['removed']} törölve",
            'data' => $result,
        ]);
    }

    /**
     * Export persons with photos
     * GET /api/tablo-management/projects/export-persons?external_id=94
     * Returns only persons who have assigned photos
     */
    public function exportPersons(ExportPersonsRequest $request): JsonResponse
    {
        $externalId = $request->input('external_id');
        $project = TabloProject::where('external_id', $externalId)->first();

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => "Projekt nem található external_id={$externalId}",
            ], 404);
        }

        // Only get persons with photos (media_id is not null)
        $personsWithPhotos = $project->persons()
            ->whereNotNull('media_id')
            ->with('photo')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'external_id' => $externalId,
                'count' => $personsWithPhotos->count(),
                'persons' => $personsWithPhotos->map(fn ($person) => [
                    'local_id' => $person->local_id,
                    'name' => $person->name,
                    'type' => $person->type,
                    'photo_url' => $person->photo?->getUrl(),
                ])->toArray(),
            ],
        ]);
    }

    /**
     * Batch delete persons
     */
    public function batchDestroy(BatchDestroyPersonRequest $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $ids = $request->input('ids');

        // Only delete persons that belong to this project
        $deleted = $project->persons()
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted.' személy sikeresen törölve',
            'deleted_count' => $deleted,
        ]);
    }
}
