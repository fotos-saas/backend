<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\NewsfeedHelperTrait;
use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Http\Requests\Api\Tablo\CreateNewsfeedCommentRequest;
use App\Models\TabloGuestSession;
use App\Models\TabloNewsfeedComment;
use App\Models\TabloNewsfeedPost;
use App\Services\Tablo\NewsfeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Newsfeed Comment Controller
 *
 * Hírfolyam hozzászólások és reakciók kezelése.
 * Token-ból azonosítja a projektet.
 */
class NewsfeedCommentController extends BaseTabloController
{
    use NewsfeedHelperTrait;
    use ResolvesTabloProject;

    public function __construct(
        protected NewsfeedService $newsfeedService
    ) {}

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
    public function createComment(CreateNewsfeedCommentRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

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
}
