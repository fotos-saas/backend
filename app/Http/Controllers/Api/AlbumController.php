<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use App\Services\CompreFaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    /**
     * Get all albums (with optional userId filter)
     */
    public function index(Request $request)
    {
        $query = Album::with(['photos', 'createdBy', 'workSessions']);

        // Album láthatóság CSAK work session alapján!
        // "Azé az album, aki látja a session-t" - NEM created_by_user_id alapján!
        if ($request->user()) {
            $query->whereHas('workSessions', function ($ws) use ($request) {
                $ws->whereIn('work_sessions.id', function ($subQuery) use ($request) {
                    $subQuery->select('work_session_id')
                        ->from('work_session_user')
                        ->where('user_id', $request->user()->id);
                });
            });
        }

        $albums = $query->withCount('photos')->get();

        return AlbumResource::collection($albums);
    }

    /**
     * Get single album with details
     */
    public function show(Album $album)
    {
        $album->load(['photos', 'createdBy', 'class', 'workSessions', 'schoolClasses']);

        return new AlbumResource($album);
    }

    /**
     * Create new album
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,archived,draft'],
            'flags' => ['nullable', 'array'],
            'flags.workflow' => ['nullable', 'string'],
            'flags.allowRetouch' => ['nullable', 'boolean'],
            'flags.allowGuestShare' => ['nullable', 'boolean'],
            'flags.enableCoupons' => ['nullable', 'boolean'],
            'flags.maxSelectable' => ['nullable', 'integer'],
            'flags.accessMode' => ['nullable', 'in:viewer,buyer,selector'],
        ]);

        $album = Album::create([
            'name' => $validated['name'],
            'title' => $validated['title'] ?? $validated['name'],
            'created_by_user_id' => $request->user()?->id,
            'date' => $validated['date'] ?? now(),
            'status' => $validated['status'] ?? 'active',
            'flags' => $validated['flags'] ?? Album::getDefaultFlags(),
        ]);

        return new AlbumResource($album->load('createdBy'));
    }

    /**
     * Trigger face clustering for album
     */
    public function clusterFaces(Album $album, CompreFaceService $service): JsonResponse
    {
        try {
            $groups = $service->clusterFacesWithVerification($album);

            return response()->json([
                'success' => true,
                'groups' => $groups,
                'message' => 'Arcklaszterezés sikeres',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba történt: '.$e->getMessage(),
            ], 500);
        }
    }
}
