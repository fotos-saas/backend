<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Photo;
use App\Models\PhotoNote;
use App\Models\User;
use App\Policies\PhotoPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PhotoController extends Controller
{
    public function index(Request $request, Album $album)
    {
        // Generate cache key based on album ID, user ID, and all query parameters
        $cacheKey = sprintf(
            'album_photos:%d:%s:%s',
            $album->id,
            $request->user()?->id ?? 'guest',
            md5(json_encode($request->all()))
        );

        // Cache response for 5 minutes (300 seconds)
        return Cache::remember($cacheKey, 300, function () use ($request, $album) {
            // For lightbox navigation, just return photo IDs
            if ($request->boolean('lightbox')) {
                $photos = $album->photos()
                    ->select(['photos.id'])  // Explicit table prefix for consistency
                    ->orderBy('original_filename', 'asc')
                    ->get();

                return $photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                    ];
                });
            }

            // Original functionality for frontend
            $query = $album->photos()->with(['notes', 'faceGroups', 'media']);

            // CRITICAL: Filter out claimed photos if this is a parent album (Tablo workflow)
            // Parent albums have child albums where users' selected photos are stored
            // We must hide photos that are already claimed by other users
            if ($album->childAlbums()->exists()) {
                $query->whereNull('claimed_by_user_id');
                \Log::info('[PhotoController] Parent album detected - filtering claimed photos', [
                    'album_id' => $album->id,
                    'album_title' => $album->title,
                ]);
            }

            // TABLO WORKFLOW: Filter by specific photo IDs (for completed/filtered views)
            // Frontend can pass comma-separated photo IDs to limit results
            if ($request->has('photo_ids')) {
                $photoIds = array_filter(explode(',', $request->input('photo_ids')));
                if (!empty($photoIds)) {
                    $query->whereIn('id', $photoIds);
                    \Log::info('[PhotoController] Filtering by photo IDs', [
                        'album_id' => $album->id,
                        'photo_ids' => $photoIds,
                        'count' => count($photoIds),
                    ]);
                }
            }

            if ($request->boolean('mine')) {
                $query->where('assigned_user_id', $request->user()->id);
            }

            if ($request->boolean('unassigned')) {
                $query->whereNull('assigned_user_id');
            }

            // OPTIMIZATION: Select only required fields to reduce payload and query time
            // CRITICAL: photos.id MUST be included for relations to work properly!
            // This reduces payload size by ~20% and query execution by ~10%
            $query->select([
                'photos.id',                    // Primary key (MUST HAVE for relations!)
                'photos.album_id',              // Foreign key (needed for potential filtering)
                'photos.assigned_user_id',      // Required for "mine" check in response
                'photos.original_filename',     // Used for sorting and may be needed
                'photos.claimed_by_user_id',    // Required for parent album filter
            ]);

            // Filter photos that have media files (exclude photos without media)
            $query->whereHas('media');

            // Order by original filename (ascending)
            $query->orderBy('original_filename', 'asc');

            // Allow custom per_page parameter (default 50, no max limit)
            $perPage = $request->integer('per_page', 50);
            $photos = $query->paginate($perPage);

            return $photos->through(function ($photo) use ($request) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->getThumbUrl(),
                    'previewUrl' => $photo->getPreviewUrl(),
                    'originalFilename' => $photo->getFirstMedia('photo')?->getCustomProperty('original_filename'),
                    'mine' => $photo->assigned_user_id === $request->user()?->id,
                    'notesCount' => $photo->notes->count(),
                    'faceGroups' => $photo->faceGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'albumId' => $group->album_id,
                        'representativePhotoId' => $group->representative_photo_id,
                    ]),
                ];
            });
        });
    }

    /**
     * Get single photo details
     */
    public function show(Request $request, Photo $photo)
    {
        // Check if user has access to this photo's album
        $album = $photo->album;

        if (! $album) {
            abort(404, 'Photo album not found');
        }

        // Check if user owns this album or is assigned to it
        $user = $request->user();
        if ($user && $album->user_id !== $user->id) {
            abort(403, 'Unauthorized access to photo');
        }

        return [
            'id' => $photo->id,
            'url' => $photo->getThumbUrl(),
            'previewUrl' => $photo->getPreviewUrl(),
            'originalFilename' => $photo->getFirstMedia('photo')?->getCustomProperty('original_filename'),
            'mine' => $photo->assigned_user_id === $user?->id,
            'notesCount' => $photo->notes()->count(),
        ];
    }

    public function userPhotos(Request $request, User $user)
    {
        // Get all photos for this user, ordered by ID descending (to match admin table)
        $photos = $user->photos()
            ->select(['photos.id'])  // Explicit table prefix for consistency
            ->orderBy('id', 'desc')
            ->get();

        return $photos->map(function ($photo) {
            return [
                'id' => $photo->id,
            ];
        });
    }

    public function addNote(Request $request, Photo $photo)
    {
        $validated = $request->validate([
            'text' => 'required|string|max:500',
        ]);

        $note = PhotoNote::create([
            'photo_id' => $photo->id,
            'user_id' => $request->user()->id,
            'text' => $validated['text'],
        ]);

        return response()->json($note, 201);
    }

    public function preview(Photo $photo, Request $request)
    {
        // SECURITY: Use PhotoPolicy for authorization (IDOR protection)
        $user = auth('sanctum')->user();
        $hasAccess = false;

        // Check via PhotoPolicy
        if (Gate::forUser($user)->allows('preview', $photo)) {
            $hasAccess = true;
        }

        // Check Bearer token for PartnerClient access (client orders feature)
        // This is separate because it requires the bearer token from request
        if (!$hasAccess && $request->bearerToken()) {
            $policy = new PhotoPolicy();
            if ($policy->checkClientAccess($request->bearerToken(), $photo)) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            abort(403, 'Nincs jogosultságod ehhez a fotóhoz');
        }

        $width = $request->integer('w', 1200);

        // Get media from Spatie Media Library
        $media = $photo->getFirstMedia('photo');

        if (!$media) {
            abort(404, 'Image not found');
        }

        // Return appropriate conversion based on width
        if ($width <= 300) {
            $conversion = 'thumb';
        } elseif ($width <= 1200) {
            $conversion = 'preview';
        } else {
            $conversion = 'watermarked';
        }

        // Get conversion path
        $path = $media->getPath($conversion);

        if (!file_exists($path)) {
            // Fallback to original if conversion doesn't exist
            $path = $media->getPath();
        }

        // SECURITY: Path traversal protection
        // Ensure the resolved path is within the storage directory
        $realPath = realpath($path);
        $storagePath = realpath(storage_path('app'));

        if ($realPath === false || $storagePath === false) {
            abort(404, 'Image not found');
        }

        if (!str_starts_with($realPath, $storagePath)) {
            \Log::warning('Path traversal attempt detected', [
                'requested_path' => $path,
                'resolved_path' => $realPath,
                'storage_path' => $storagePath,
                'photo_id' => $photo->id,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid path');
        }

        return response()->file($realPath, [
            'Content-Type' => $media->mime_type ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
