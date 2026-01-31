<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Models\TabloGuestSession;
use App\Models\TabloNewsfeedComment;
use App\Models\TabloNewsfeedMedia;
use App\Models\TabloNewsfeedPost;
use App\Services\Tablo\NewsfeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Newsfeed Controller
 *
 * Hírfolyam API végpontok (bejelentések, események).
 * Token-ból azonosítja a projektet.
 */
class NewsfeedController extends BaseTabloController
{
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
     * Toggle reaction on post.
     * POST /api/tablo-frontend/newsfeed/{id}/like
     */
    public function toggleLike(Request $request, int $id): JsonResponse
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

        [$likerType, $likerId] = $this->getAuthorInfo($request);

        if (! $likerId) {
            return $this->unauthorizedResponse('Hiányzó felhasználó azonosító.');
        }

        // Check if banned
        if ($likerType === TabloNewsfeedPost::AUTHOR_TYPE_GUEST) {
            $guestSession = TabloGuestSession::find($likerId);
            if ($guestSession?->is_banned) {
                return $this->forbiddenResponse('A reakció nem engedélyezett.');
            }
        }

        // Get reaction from request (default: ❤️)
        $reaction = $request->input('reaction', '❤️');

        $result = $this->newsfeedService->toggleReaction($post, $likerType, $likerId, $reaction);

        return $this->successResponse($result);
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
     * Get comments for post.
     * GET /api/tablo-frontend/newsfeed/{id}/comments
     */
    public function getComments(Request $request, int $id): JsonResponse
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

        $perPage = min($request->integer('per_page', 20), 50);
        $comments = $this->newsfeedService->getComments($post, $perPage);

        [$currentUserType, $currentUserId] = $this->getCurrentUserInfo($request);

        return $this->successResponse(
            $comments->through(fn ($c) => $this->formatComment($c, $currentUserType, $currentUserId, true))
        );
    }

    /**
     * Create comment.
     * POST /api/tablo-frontend/newsfeed/{id}/comments
     */
    public function createComment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000|min:1',
            'parent_id' => 'nullable|integer|exists:tablo_newsfeed_comments,id',
        ], [
            'content.required' => 'A hozzászólás megadása kötelező.',
            'content.max' => 'A hozzászólás maximum 1000 karakter lehet.',
            'parent_id.exists' => 'A szülő hozzászólás nem található.',
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

        // Ha van parent_id, ellenőrizzük, hogy ugyanahhoz a poszthoz tartozik-e
        if (! empty($validated['parent_id'])) {
            $parentComment = TabloNewsfeedComment::find($validated['parent_id']);
            if (! $parentComment || $parentComment->tablo_newsfeed_post_id !== $post->id) {
                return $this->validationErrorResponse('A szülő hozzászólás nem ehhez a bejegyzéshez tartozik.');
            }
        }

        [$authorType, $authorId] = $this->getAuthorInfo($request);

        if (! $authorId) {
            return $this->unauthorizedResponse('Hiányzó felhasználó azonosító.');
        }

        // Check if banned
        if ($authorType === TabloNewsfeedComment::AUTHOR_TYPE_GUEST) {
            $guestSession = TabloGuestSession::find($authorId);
            if ($guestSession?->is_banned) {
                return $this->forbiddenResponse('A hozzászólás nem engedélyezett.');
            }
        }

        $comment = $this->newsfeedService->createComment(
            $post,
            $validated['content'],
            $authorType,
            $authorId,
            $validated['parent_id'] ?? null
        );

        return $this->successResponse(
            $this->formatComment($comment, $authorType, $authorId),
            'Hozzászólás sikeresen létrehozva!',
            201
        );
    }

    /**
     * Toggle reaction on comment.
     * POST /api/tablo-frontend/newsfeed-comments/{id}/like
     */
    public function toggleCommentLike(Request $request, int $id): JsonResponse
    {
        $comment = $this->findThroughRelation(
            TabloNewsfeedComment::class,
            $id,
            $request,
            'post',
            'Hozzászólás nem található'
        );

        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        [$likerType, $likerId] = $this->getAuthorInfo($request);

        if (! $likerId) {
            return $this->unauthorizedResponse('Hiányzó felhasználó azonosító.');
        }

        // Check if banned
        if ($likerType === TabloNewsfeedComment::AUTHOR_TYPE_GUEST) {
            $guestSession = TabloGuestSession::find($likerId);
            if ($guestSession?->is_banned) {
                return $this->forbiddenResponse('A reakció nem engedélyezett.');
            }
        }

        // Get reaction from request (default: ❤️)
        $reaction = $request->input('reaction', '❤️');

        $result = $comment->toggleReaction($likerType, $likerId, $reaction);

        return $this->successResponse($result);
    }

    /**
     * Delete comment.
     * DELETE /api/tablo-frontend/newsfeed-comments/{id}
     */
    public function deleteComment(Request $request, int $id): JsonResponse
    {
        $comment = $this->findThroughRelation(
            TabloNewsfeedComment::class,
            $id,
            $request,
            'post',
            'Hozzászólás nem található'
        );

        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        // Check permissions
        [$authorType, $authorId] = $this->getAuthorInfo($request);
        $isAdmin = $this->isContact($request);

        if (! $isAdmin && ! $comment->canBeDeletedBy($authorType, $authorId)) {
            return $this->forbiddenResponse('Nincs jogosultságod törölni.');
        }

        $this->newsfeedService->deleteComment($comment);

        return $this->successResponse(null, 'Hozzászólás sikeresen törölve!');
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

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Get author info from request (contact or guest).
     */
    private function getAuthorInfo(Request $request): array
    {
        // Check if contact token
        if ($this->isContact($request)) {
            return [TabloNewsfeedPost::AUTHOR_TYPE_CONTACT, $this->getContactId($request)];
        }

        // Check guest session
        $guestSession = $this->getGuestSession($request);
        if ($guestSession && ! $guestSession->is_banned) {
            return [TabloNewsfeedPost::AUTHOR_TYPE_GUEST, $guestSession->id];
        }

        return [null, null];
    }

    /**
     * Get current user info for like status.
     */
    private function getCurrentUserInfo(Request $request): array
    {
        return $this->getAuthorInfo($request);
    }

    /**
     * Format post for API response.
     */
    private function formatPost(TabloNewsfeedPost $post, ?string $currentUserType, ?int $currentUserId): array
    {
        $hasLiked = false;
        $userReaction = null;
        $canEdit = false;
        $canDelete = false;

        if ($currentUserType && $currentUserId) {
            $hasLiked = $post->hasLiked($currentUserType, $currentUserId);
            $userReaction = $post->getUserReaction($currentUserType, $currentUserId);
            $canEdit = $post->canBeEditedBy($currentUserType, $currentUserId);
            $canDelete = $canEdit;
        }

        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'title' => $post->title,
            'content' => $post->content,
            'event_date' => $post->event_date?->toDateString(),
            'event_time' => $post->event_time,
            'event_location' => $post->event_location,
            'author_type' => $post->author_type,
            'author_name' => $post->author_name,
            'is_pinned' => $post->is_pinned,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'has_liked' => $hasLiked,
            'user_reaction' => $userReaction,
            'reactions' => $post->getReactionsSummary(),
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'media' => $post->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'file_name' => $m->file_name,
                'is_image' => $m->is_image,
            ])->toArray(),
            'created_at' => $post->created_at->toIso8601String(),
            'updated_at' => $post->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format post with comments.
     */
    private function formatPostWithComments(TabloNewsfeedPost $post, ?string $currentUserType, ?int $currentUserId): array
    {
        $data = $this->formatPost($post, $currentUserType, $currentUserId);
        $data['comments'] = $post->comments->map(
            fn ($c) => $this->formatComment($c, $currentUserType, $currentUserId)
        )->toArray();

        return $data;
    }

    /**
     * Format comment for API response.
     */
    private function formatComment(TabloNewsfeedComment $comment, ?string $currentUserType, ?int $currentUserId, bool $includeReplies = true): array
    {
        $canDelete = false;
        $userReaction = null;

        if ($currentUserType && $currentUserId) {
            $canDelete = $comment->canBeDeletedBy($currentUserType, $currentUserId);
            $userReaction = $comment->getUserReaction($currentUserType, $currentUserId);
        }

        $data = [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author_type' => $comment->author_type,
            'author_name' => $comment->author_name,
            'content' => $comment->content,
            'is_edited' => $comment->is_edited,
            'can_delete' => $canDelete,
            'reactions' => $comment->getReactionsSummary(),
            'user_reaction' => $userReaction,
            'created_at' => $comment->created_at->toIso8601String(),
        ];

        // Include replies if this is a top-level comment
        if ($includeReplies && $comment->parent_id === null) {
            $data['replies'] = $comment->replies->map(
                fn ($reply) => $this->formatComment($reply, $currentUserType, $currentUserId, false)
            )->toArray();
        }

        return $data;
    }
}
