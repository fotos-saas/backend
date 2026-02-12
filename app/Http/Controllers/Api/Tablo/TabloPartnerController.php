<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Partner\StoreTabloPartnerRequest;
use App\Http\Requests\Api\Tablo\Partner\UpdateTabloPartnerRequest;
use App\Models\TabloPartner;
use Illuminate\Http\JsonResponse;

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
    public function store(StoreTabloPartnerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $partner = TabloPartner::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'local_id' => $validated['local_id'] ?? null,
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
    public function update(UpdateTabloPartnerRequest $request, int $id): JsonResponse
    {
        $partner = TabloPartner::find($id);

        if (! $partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner nem található',
            ], 404);
        }

        $partner->update($request->validated());

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
