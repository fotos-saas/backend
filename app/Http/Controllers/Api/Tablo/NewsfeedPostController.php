<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\NewsfeedHelperTrait;
use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Http\Requests\Api\Tablo\StoreNewsfeedPostRequest;
use App\Http\Requests\Api\Tablo\UpdateNewsfeedPostRequest;
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

        $post->load(['media', 'comments']);

        [$currentUserType, $currentUserId] = $this->getCurrentUserInfo($request);

        return $this->successResponse(
            $this->formatPostWithComments($post, $currentUserType, $currentUserId)
        );
    }

    /**
     * Create new post.
     * POST /api/tablo-frontend/newsfeed
     */
    public function store(StoreNewsfeedPostRequest $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        [$authorType, $authorId] = $this->getAuthorInfo($request);

        if (! $authorId) {
            return $this->unauthorizedResponse('Hiányzó felhasználó azonosító.');
        }

        try {
            $post = $this->newsfeedService->createPost(
                $project,
                $request->validated(),
                $authorType,
                $authorId,
                $request->file('media', [])
            );

            return $this->successResponse(
                $this->formatPost($post, $authorType, $authorId),
                'Bejegyzés sikeresen létrehozva!',
                201
            );
        } catch (\InvalidArgumentException $e) {
            // Business logic validation - safe to expose
            return $this->validationErrorResponse($e->getMessage());
        }
    }

    /**
     * Update post.
     * PUT/POST /api/tablo-frontend/newsfeed/{id}
     */
    public function update(UpdateNewsfeedPostRequest $request, int $id): JsonResponse
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

        // Jogosultság ellenőrzés
        [$authorType, $authorId] = $this->getAuthorInfo($request);
        $isAdmin = $this->isContact($request);

        if (! $isAdmin && ! $post->canBeEditedBy($authorType, $authorId)) {
            return $this->forbiddenResponse('Nincs jogosultságod szerkeszteni.');
        }

        try {
            $post = $this->newsfeedService->updatePostWithMedia(
                $post,
                $request->validated(),
                $request->file('media', [])
            );

            return $this->successResponse(
                $this->formatPost($post, $authorType, $authorId),
                'Bejegyzés sikeresen frissítve!'
            );
        } catch (\InvalidArgumentException $e) {
            // Business logic validation - safe to expose
            return $this->validationErrorResponse($e->getMessage());
        }
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
