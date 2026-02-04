<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'local_id' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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
    public function batchStore(Request $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'persons' => 'required|array|min:1',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.local_id' => 'nullable|string|max:255',
            'persons.*.note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $created = [];

        DB::transaction(function () use ($project, $request, &$created) {
            foreach ($request->input('persons') as $personData) {
                $person = $project->persons()->create([
                    'name' => $personData['name'],
                    'local_id' => $personData['local_id'] ?? null,
                    'note' => $personData['note'] ?? null,
                ]);

                $created[] = [
                    'id' => $person->id,
                    'name' => $person->name,
                    'local_id' => $person->local_id,
                    'note' => $person->note,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created).' személy sikeresen hozzáadva',
            'data' => $created,
        ], 201);
    }

    /**
     * Update person
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $person = TabloPerson::find($id);

        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'Személy nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'local_id' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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
    public function syncPersons(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer',
            'persons' => 'required|array',
            'persons.*.name' => 'required|string|max:255',
            'persons.*.local_id' => 'required|string|max:255',
            'persons.*.type' => 'nullable|string|in:student,teacher',
            'persons.*.position' => 'nullable|integer',
            'persons.*.note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $externalId = (string) $request->input('project_id');
        $project = TabloProject::where('external_id', $externalId)->first();

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => "Projekt nem található external_id={$externalId}",
            ], 404);
        }

        $incomingPersons = collect($request->input('persons'));
        $added = 0;
        $removed = 0;
        $updated = 0;

        DB::transaction(function () use ($project, $incomingPersons, &$added, &$removed, &$updated) {
            // Get current persons indexed by local_id
            $currentPersons = $project->persons()->get()->keyBy('local_id');

            // Get incoming local_ids
            $incomingLocalIds = $incomingPersons->pluck('local_id')->toArray();

            // Remove persons not in incoming list
            foreach ($currentPersons as $localId => $current) {
                if (! in_array($localId, $incomingLocalIds)) {
                    $current->delete();
                    $removed++;
                }
            }

            // Add or update persons
            foreach ($incomingPersons as $index => $person) {
                $localId = $person['local_id'];
                $position = $person['position'] ?? $index;
                $type = $person['type'] ?? 'student';
                $existing = $currentPersons->get($localId);

                if ($existing) {
                    // Update if data changed
                    $needsUpdate = $existing->name !== $person['name']
                        || $existing->note !== ($person['note'] ?? null)
                        || $existing->position !== $position
                        || $existing->type !== $type;

                    if ($needsUpdate) {
                        $existing->update([
                            'name' => $person['name'],
                            'note' => $person['note'] ?? null,
                            'position' => $position,
                            'type' => $type,
                        ]);
                        $updated++;
                    }
                } else {
                    // Add new
                    $project->persons()->create([
                        'name' => $person['name'],
                        'local_id' => $localId,
                        'note' => $person['note'] ?? null,
                        'position' => $position,
                        'type' => $type,
                    ]);
                    $added++;
                }
            }
        });

        // Get updated list ordered by position
        $updatedPersons = $project->persons()->orderBy('position')->get();

        return response()->json([
            'success' => true,
            'message' => "Szinkronizálás kész: +{$added} hozzáadva, ~{$updated} frissítve, -{$removed} törölve",
            'data' => [
                'project_id' => $project->id,
                'external_id' => $project->external_id,
                'added' => $added,
                'updated' => $updated,
                'removed' => $removed,
                'total' => $updatedPersons->count(),
                'persons' => $updatedPersons->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'type' => $m->type,
                    'local_id' => $m->local_id,
                    'note' => $m->note,
                    'position' => $m->position,
                ])->toArray(),
            ],
        ]);
    }

    /**
     * Export persons with photos
     * GET /api/tablo-management/projects/export-persons?external_id=94
     * Returns only persons who have assigned photos
     */
    public function exportPersons(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

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
    public function batchDestroy(Request $request, int $projectId): JsonResponse
    {
        $project = TabloProject::find($projectId);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
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
