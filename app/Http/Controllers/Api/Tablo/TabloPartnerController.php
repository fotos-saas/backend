<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TabloPartnerController extends Controller
{
    /**
     * List all partners
     */
    public function index(): JsonResponse
    {
        $partners = TabloPartner::withCount('projects')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $partners->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'local_id' => $p->local_id,
                'projects_count' => $p->projects_count,
                'created_at' => $p->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get single partner
     */
    public function show(int $id): JsonResponse
    {
        $partner = TabloPartner::withCount('projects')->find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner nem található',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'local_id' => $partner->local_id,
                'projects_count' => $partner->projects_count,
                'created_at' => $partner->created_at->toIso8601String(),
                'updated_at' => $partner->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create new partner
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tablo_partners,slug',
            'local_id' => 'nullable|string|max:255|unique:tablo_partners,local_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $partner = TabloPartner::create([
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'local_id' => $request->input('local_id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partner sikeresen létrehozva',
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'local_id' => $partner->local_id,
            ],
        ], 201);
    }

    /**
     * Update partner
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $partner = TabloPartner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner nem található',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tablo_partners,slug,'.$id,
            'local_id' => 'nullable|string|max:255|unique:tablo_partners,local_id,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validációs hiba',
                'errors' => $validator->errors(),
            ], 422);
        }

        $partner->update($request->only(['name', 'slug', 'local_id']));

        return response()->json([
            'success' => true,
            'message' => 'Partner sikeresen frissítve',
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'local_id' => $partner->local_id,
            ],
        ]);
    }

    /**
     * Delete partner
     */
    public function destroy(int $id): JsonResponse
    {
        $partner = TabloPartner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner nem található',
            ], 404);
        }

        // Check if partner has projects
        if ($partner->projects()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A partner nem törölhető, mert vannak hozzá kapcsolódó projektek',
            ], 409);
        }

        $partner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partner sikeresen törölve',
        ]);
    }
}
