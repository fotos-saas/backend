<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Models\TabloDiscussion;
use App\Models\TabloDiscussionPost;
use App\Services\Tablo\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Discussion Post Controller
 *
 * Fórum hozzászólások (post-ok) kezelése: létrehozás, szerkesztés, törlés, reakciók.
 * Token-ból azonosítja a projektet.
 */
class DiscussionPostController extends BaseTabloController
{
    use ResolvesTabloProject;

    public function __construct(
        protected DiscussionService $discussionService
    ) {}

    /**
     * Create new post (reply).
     * POST /api/tablo-frontend/discussions/{id}/posts
     */
    public function createPost(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000|min:1',
            'parent_id' => 'nullable|integer|exists:tablo_discussion_posts,id',
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|image|max:5120',
        ], [
            'content.required' => 'A tartalom megadása kötelező.',
            'media.max' => 'Maximum 3 kép csatolható.',
            'media.*.max' => 'A fájl mérete maximum 5MB lehet.',
            'media.*.image' => 'Csak képfájlok (jpg, png, gif, webp) engedélyezettek.',
        ]);

        $discussion = $this->findForProject(
            TabloDiscussion::class,
            $id,
            $request,
            'tablo_project_id',
            'Beszélgetés nem található'
        );

        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        if ($discussion->is_locked) {
            return $this->forbiddenResponse('A beszélgetés le van zárva.');
        }

        // Get author info from guest session
        $guestSession = $this->requireActiveGuestSession($request);
        if ($guestSession instanceof JsonResponse) {
            return $guestSession;
        }

        // Collect media files
        $mediaFiles = $request->file('media', []);

        $post = $this->discussionService->createPost(
            $discussion,
            $validated['content'],
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $guestSession->id,
            $validated['parent_id'] ?? null,
            $mediaFiles
        );

        return $this->successResponse(
            $this->formatPost($post, TabloDiscussionPost::AUTHOR_TYPE_GUEST, $guestSession->id),
            'Hozzászólás sikeresen létrehozva!',
            201
        );
    }

    /**
     * Update post.
     * PUT /api/tablo-frontend/posts/{id}
     */
    public function updatePost(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000|min:1',
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|image|max:5120',
            'delete_media' => 'nullable|array',
            'delete_media.*' => 'integer',
        ], [
            'media.max' => 'Maximum 3 kép csatolható.',
            'media.*.max' => 'A fájl mérete maximum 5MB lehet.',
            'media.*.image' => 'Csak képfájlok (jpg, png, gif, webp) engedélyezettek.',
        ]);

        $post = $this->findThroughRelation(
            TabloDiscussionPost::class,
            $id,
            $request,
            'discussion',
            'Hozzászólás nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        // Check author - contact (full access) vagy guest (saját post)
        $isContact = $this->isContact($request);
        $guestSession = $this->getGuestSession($request);

        $canEdit = false;

        // Contact mindig szerkeszthet
        if ($isContact) {
            $canEdit = true;
        }
        // Guest csak a saját postját szerkesztheti
        elseif ($guestSession) {
            $canEdit = $this->discussionService->canUserEditPost(
                $post,
                TabloDiscussionPost::AUTHOR_TYPE_GUEST,
                $guestSession->id
            );
        }

        if (! $canEdit) {
            return $this->forbiddenResponse('Nincs jogosultságod szerkeszteni ezt a hozzászólást.');
        }

        try {
            // Média törlés
            if (! empty($validated['delete_media'])) {
                $this->discussionService->deletePostMedia($post, $validated['delete_media']);
            }

            // Új média feltöltés
            $mediaFiles = $request->file('media', []);
            if (! empty($mediaFiles)) {
                foreach ($mediaFiles as $file) {
                    $this->discussionService->uploadMedia($post, $file);
                }
            }

            $this->discussionService->updatePost($post, $validated['content'], $isContact);

            // Return updated post with media
            $post->load('media');

            return $this->successResponse([
                'media' => $post->media->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->url,
                    'file_name' => $media->file_name,
                    'is_image' => $media->isImage(),
                ])->toArray(),
            ], 'Hozzászólás sikeresen frissítve!');
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        }
    }

    /**
     * Delete post.
     * DELETE /api/tablo-frontend/posts/{id}
     */
    public function deletePost(Request $request, int $id): JsonResponse
    {
        $post = $this->findThroughRelation(
            TabloDiscussionPost::class,
            $id,
            $request,
            'discussion',
            'Hozzászólás nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        // Check permissions
        $guestSession = $this->getGuestSession($request);
        $isModerator = $this->isContact($request);

        $canDelete = ($guestSession && $this->discussionService->canUserDeletePost(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $guestSession->id,
            $isModerator
        )) || $isModerator;

        if (! $canDelete) {
            return $this->forbiddenResponse('Nincs jogosultságod törölni ezt a hozzászólást.');
        }

        $this->discussionService->deletePost($post);

        return $this->successResponse(null, 'Hozzászólás sikeresen törölve!');
    }

    /**
     * Toggle reaction on post.
     * POST /api/tablo-frontend/posts/{id}/like
     *
     * Body: { "reaction": "heart" } (optional, default: heart)
     * Supported reactions: skull, crying, salute, heart, eyes
     */
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => 'nullable|string|in:💀,😭,🫡,❤️,👀',
        ]);

        $post = $this->findThroughRelation(
            TabloDiscussionPost::class,
            $id,
            $request,
            'discussion',
            'Hozzászólás nem található'
        );

        if ($post instanceof JsonResponse) {
            return $post;
        }

        // Get guest session (required + active)
        $guestSession = $this->requireActiveGuestSession($request);
        if ($guestSession instanceof JsonResponse) {
            return $guestSession;
        }

        $reaction = $validated['reaction'] ?? '❤️';

        $result = $this->discussionService->toggleReaction(
            $post,
            TabloDiscussionPost::AUTHOR_TYPE_GUEST,
            $guestSession->id,
            $reaction
        );

        return $this->successResponse([
            'has_reacted' => $result['added'],
            'user_reaction' => $result['reaction'],
            'reactions' => $result['reactions'],
            'likes_count' => $result['likesCount'],
            // Legacy compatibility
            'is_liked' => $result['added'],
        ]);
    }

    /**
     * Format post for API response.
     */
    private function formatPost(TabloDiscussionPost $post, ?string $currentUserType, ?int $currentUserId): array
    {
        $isLiked = false;
        $userReaction = null;
        $canEdit = false;
        $canDelete = false;

        // Contact mindig szerkeszthet és törölhet
        if ($currentUserType === TabloDiscussionPost::AUTHOR_TYPE_CONTACT) {
            $canEdit = true;
            $canDelete = true;
        } elseif ($currentUserType && $currentUserId) {
            // Guest: service ellenőrzi (15 perc, saját post)
            $canEdit = $this->discussionService->canUserEditPost($post, $currentUserType, $currentUserId);
            $canDelete = $this->discussionService->canUserDeletePost($post, $currentUserType, $currentUserId);
        }

        // Like/reaction ellenőrzés mindkét típusra
        if ($currentUserType && $currentUserId) {
            $isLiked = $post->isLikedBy($currentUserType, $currentUserId);
            $userReaction = $post->getUserReaction($currentUserType, $currentUserId);
        }

        return [
            'id' => $post->id,
            'author_name' => $post->author_name,
            'is_author_contact' => $post->isAuthorContact(),
            'content' => $post->content,
            'mentions' => $post->mentions ?? [],
            'is_edited' => $post->is_edited,
            'edited_at' => $post->edited_at?->toIso8601String(),
            'likes_count' => $post->likes_count,
            'is_liked' => $isLiked,
            'user_reaction' => $userReaction,
            'reactions' => $post->getReactionsSummary(),
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'parent_id' => $post->parent_id,
            'replies' => $post->replies->map(fn ($reply) => $this->formatPost($reply, $currentUserType, $currentUserId))->toArray(),
            'media' => $post->media->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->url,
                'file_name' => $media->file_name,
                'is_image' => $media->isImage(),
            ])->toArray(),
            'created_at' => $post->created_at->toIso8601String(),
        ];
    }
}
