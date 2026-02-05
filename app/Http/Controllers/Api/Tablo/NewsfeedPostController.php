<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\NewsfeedHelperTrait;
use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Models\TabloNewsfeedMedia;
use App\Models\TabloNewsfeedPost;
use App\Services\Tablo\NewsfeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Newsfeed Post Controller
 *
 * Hírfolyam bejegyzések CRUD + média kezelés.
 * Token-ból azonosítja a projektet.
 */
class NewsfeedPostController extends BaseTabloController
{
    use NewsfeedHelperTrait;
    use ResolvesTabloProject;

    public function __construct(
        protected NewsfeedService $newsfeedService
    ) {}

    /**
     * Get newsfeed posts list.
     * GET /api/tablo-frontend/newsfeed
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $filters = [
            'type' => $request->get('type'),
            'search' => $request->get('search'),
            'per_page' => $request->get('per_page', 15),
        ];

        $posts = $this->newsfeedService->getPosts($project, $filters);

        // Get current user info for like status
        [$currentUserType, $currentUserId] = $this->getCurrentUserInfo($request);

        return $this->successResponse(
            $posts->through(fn ($post) => $this->formatPost($post, $currentUserType, $currentUserId))
        );
    }

    /**
     * Get upcoming events.
     * GET /api/tablo-frontend/newsfeed/events/upcoming
     */
    public function upcomingEvents(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        $limit = min($request->integer('limit', 5), 20);
        $events = $this->newsfeedService->getUpcomingEvents($project, $limit);

        return $this->successResponse(
            $events->map(fn ($post) => $this->formatPost($post, null, null))
        );
    }

    /**
     * Get single post details.
     * GET /api/tablo-frontend/newsfeed/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = $this->findForProject(
            TabloNewsfeedPost::class,
            $id,
            $request,
            'tablo_project_id',
            'Bejegyzés nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        // Load relations
        $post->load(['media', 'comments']);

        // Get current user info
        [$currentUserType, $currentUserId] = $this->getCurrentUserInfo($request);

        return $this->successResponse(
            $this->formatPostWithComments($post, $currentUserType, $currentUserId)
        );
    }

    /**
     * Create new post.
     * POST /api/tablo-frontend/newsfeed
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_type' => 'required|in:announcement,event',
            'title' => 'required|string|max:255|min:3',
            'content' => 'nullable|string|max:5000',
            'event_date' => 'required_if:post_type,event|nullable|date|after:today',
            'event_time' => 'nullable|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'media' => 'nullable|array|max:5',
            'media.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4|max:10240',
        ], [
            'title.required' => 'A cím megadása kötelező.',
            'title.min' => 'A cím legalább 3 karakter legyen.',
            'event_date.required_if' => 'Eseménynél a dátum megadása kötelező.',
            'event_date.after' => 'Az esemény dátuma jövőbeli kell legyen.',
            'media.max' => 'Maximum 5 fájl csatolható.',
            'media.*.max' => 'A fájl mérete maximum 10MB lehet.',
        ]);

        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        // Get author info
        [$authorType, $authorId] = $this->getAuthorInfo($request);

        if (! $authorId) {
            return $this->unauthorizedResponse('Hiányzó felhasználó azonosító.');
        }

        $mediaFiles = $request->file('media', []);

        try {
            $post = $this->newsfeedService->createPost(
                $project,
                $validated,
                $authorType,
                $authorId,
                $mediaFiles
            );

            return $this->successResponse(
                $this->formatPost($post, $authorType, $authorId),
                'Bejegyzés sikeresen létrehozva!',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        }
    }

    /**
     * Update post.
     * PUT/POST /api/tablo-frontend/newsfeed/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255|min:3',
            'content' => 'nullable|string|max:5000',
            'event_date' => 'nullable|date',
            'event_time' => 'nullable|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4|max:10240',
        ], [
            'media.*.max' => 'A fájl mérete maximum 10MB lehet.',
        ]);

        $post = $this->findForProject(
            TabloNewsfeedPost::class,
            $id,
            $request,
            'tablo_project_id',
            'Bejegyzés nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        $post->load('media');

        // Check permissions
        [$authorType, $authorId] = $this->getAuthorInfo($request);
        $isAdmin = $this->isContact($request);

        if (! $isAdmin && ! $post->canBeEditedBy($authorType, $authorId)) {
            return $this->forbiddenResponse('Nincs jogosultságod szerkeszteni.');
        }

        // Média limit ellenőrzés
        $mediaFiles = $request->file('media', []);
        $existingMediaCount = $post->media->count();
        $newMediaCount = count($mediaFiles);
        $maxMediaCount = 5;

        if ($existingMediaCount + $newMediaCount > $maxMediaCount) {
            $available = $maxMediaCount - $existingMediaCount;

            return $this->validationErrorResponse(
                "Maximum {$maxMediaCount} média csatolható. Jelenlegi: {$existingMediaCount}, hozzáadható: {$available}"
            );
        }

        // Poszt adatok frissítése
        $post = $this->newsfeedService->updatePost($post, $validated);

        // Új médiák feltöltése
        if (! empty($mediaFiles)) {
            $currentSortOrder = $existingMediaCount;
            foreach ($mediaFiles as $file) {
                try {
                    $this->newsfeedService->uploadMedia($post, $file, $currentSortOrder);
                    $currentSortOrder++;
                } catch (\InvalidArgumentException $e) {
                    return $this->validationErrorResponse($e->getMessage());
                }
            }
            $post = $post->fresh(['media']);
        }

        return $this->successResponse(
            $this->formatPost($post, $authorType, $authorId),
            'Bejegyzés sikeresen frissítve!'
        );
    }

    /**
     * Delete post.
     * DELETE /api/tablo-frontend/newsfeed/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = $this->findForProject(
            TabloNewsfeedPost::class,
            $id,
            $request,
            'tablo_project_id',
            'Bejegyzés nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        // Check permissions
        [$authorType, $authorId] = $this->getAuthorInfo($request);
        $isAdmin = $this->isContact($request);

        if (! $isAdmin && ! $post->canBeEditedBy($authorType, $authorId)) {
            return $this->forbiddenResponse('Nincs jogosultságod törölni.');
        }

        $this->newsfeedService->deletePost($post);

        return $this->successResponse(null, 'Bejegyzés sikeresen törölve!');
    }

    /**
     * Pin post (admin only).
     * POST /api/tablo-frontend/newsfeed/{id}/pin
     */
    public function pin(Request $request, int $id): JsonResponse
    {
        // Only contact (admin) can pin
        if (! $this->isContact($request)) {
            return $this->forbiddenResponse('Nincs jogosultságod.');
        }

        $post = $this->findForProject(
            TabloNewsfeedPost::class,
            $id,
            $request,
            'tablo_project_id',
            'Bejegyzés nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        $this->newsfeedService->pinPost($post);

        return $this->successResponse(null, 'Bejegyzés kitűzve!');
    }

    /**
     * Unpin post (admin only).
     * POST /api/tablo-frontend/newsfeed/{id}/unpin
     */
    public function unpin(Request $request, int $id): JsonResponse
    {
        // Only contact (admin) can unpin
        if (! $this->isContact($request)) {
            return $this->forbiddenResponse('Nincs jogosultságod.');
        }

        $post = $this->findForProject(
            TabloNewsfeedPost::class,
            $id,
            $request,
            'tablo_project_id',
            'Bejegyzés nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        $this->newsfeedService->unpinPost($post);

        return $this->successResponse(null, 'Bejegyzés levéve a kitűzésből!');
    }

    /**
     * Delete media from post.
     * DELETE /api/tablo-frontend/newsfeed/media/{mediaId}
     */
    public function deleteMedia(Request $request, int $mediaId): JsonResponse
    {
        $media = $this->findThroughRelation(
            TabloNewsfeedMedia::class,
            $mediaId,
            $request,
            'post',
            'Média nem található'
        );

        if ($media instanceof JsonResponse) {
            return $media;
        }

        // Jogosultság ellenőrzés
        [$authorType, $authorId] = $this->getAuthorInfo($request);
        $isAdmin = $this->isContact($request);
        $post = $media->post;

        if (! $isAdmin && ! $post->canBeEditedBy($authorType, $authorId)) {
            return $this->forbiddenResponse('Nincs jogosultságod törölni ezt a médiát.');
        }

        $this->newsfeedService->deleteMedia($media);

        return $this->successResponse(null, 'Média sikeresen törölve!');
    }
}
