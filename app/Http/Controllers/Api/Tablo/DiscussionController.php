<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Api\Tablo\Traits\ResolvesTabloProject;
use App\Models\TabloDiscussion;
use App\Models\TabloDiscussionPost;
use App\Services\Tablo\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Discussion Controller
 *
 * Fórum/beszélgetés kezelés API végpontok.
 * Token-ból azonosítja a projektet.
 */
class DiscussionController extends BaseTabloController
{
    use ResolvesTabloProject;

    public function __construct(
        protected DiscussionService $discussionService
    ) {}

    /**
     * Get discussions list with optional filtering.
     * GET /api/tablo-frontend/discussions
     */
    public function index(Request $request): JsonResponse
    {
        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        // Szűrési paraméterek
        $filters = [
            'search' => $request->get('search'),
            'template_id' => $request->get('template_id'),
            'sort_by' => $request->get('sort_by', 'latest'),
        ];

        $discussions = $this->discussionService->getByProject($project, $filters);

        return $this->successResponse(
            $discussions->map(fn ($discussion) => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'slug' => $discussion->slug,
                'creator_name' => $discussion->creator_name,
                'is_creator_contact' => $discussion->isCreatorContact(),
                'template_id' => $discussion->tablo_sample_template_id,
                'template_name' => $discussion->template?->name,
                'is_pinned' => $discussion->is_pinned,
                'is_locked' => $discussion->is_locked,
                'posts_count' => $discussion->posts_count,
                'views_count' => $discussion->views_count,
                'last_post_at' => $discussion->last_post?->created_at?->toIso8601String(),
                'created_at' => $discussion->created_at->toIso8601String(),
            ])
        );
    }

    /**
     * Get discussion details with posts.
     * GET /api/tablo-frontend/discussions/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $discussion = $this->findBySlugForProject(
            TabloDiscussion::class,
            $slug,
            $request,
            'tablo_project_id',
            'Beszélgetés nem található'
        );

        if ($discussion instanceof JsonResponse) {
            return $discussion;
        }

        $perPage = $request->integer('per_page', 20);
        $result = $this->discussionService->getWithPosts($discussion, $perPage);

        // Get current user info for like status and edit permissions
        $isContact = $this->isContact($request);
        $guestSession = $this->getGuestSession($request);
        $currentUserType = null;
        $currentUserId = null;

        if ($isContact) {
            // Contact felhasználó - teljes hozzáférés
            $currentUserType = TabloDiscussionPost::AUTHOR_TYPE_CONTACT;
            $currentUserId = $this->getContactId($request) ?? 0;
        } elseif ($guestSession && ! $guestSession->is_banned) {
            // Guest felhasználó
            $currentUserType = TabloDiscussionPost::AUTHOR_TYPE_GUEST;
            $currentUserId = $guestSession->id;
        }

        return $this->successResponse([
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'slug' => $discussion->slug,
                'creator_name' => $discussion->creator_name,
                'is_creator_contact' => $discussion->isCreatorContact(),
                'template_id' => $discussion->tablo_sample_template_id,
                'template_name' => $discussion->template?->name,
                'is_pinned' => $discussion->is_pinned,
                'is_locked' => $discussion->is_locked,
                'can_add_posts' => $discussion->canAddPosts(),
                'posts_count' => $discussion->posts_count,
                'views_count' => $discussion->views_count,
                'created_at' => $discussion->created_at->toIso8601String(),
            ],
            'posts' => $result['posts']->through(fn ($post) => $this->formatPost($post, $currentUserType, $currentUserId)),
        ]);
    }

    /**
     * Create new discussion (contact only).
     * POST /api/tablo-frontend/discussions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|min:3',
            'content' => 'required|string|max:10000|min:10',
            'template_id' => 'nullable|integer|exists:tablo_sample_templates,id',
        ], [
            'title.required' => 'A cím megadása kötelező.',
            'title.min' => 'A cím legalább 3 karakter legyen.',
            'content.required' => 'A tartalom megadása kötelező.',
            'content.min' => 'A tartalom legalább 10 karakter legyen.',
        ]);

        $project = $this->getProjectOrFail($request);
        if ($project instanceof JsonResponse) {
            return $project;
        }

        // Only contact can create discussions
        $creatorType = TabloDiscussion::CREATOR_TYPE_CONTACT;
        $creatorId = $this->getContactId($request) ?? 0;

        $discussion = $this->discussionService->createDiscussion(
            $project,
            $validated['title'],
            $validated['content'],
            $creatorType,
            $creatorId,
            $validated['template_id'] ?? null
        );

        return $this->successResponse([
            'id' => $discussion->id,
            'slug' => $discussion->slug,
        ], 'Beszélgetés sikeresen létrehozva!', 201);
    }

    /**
     * Update discussion (contact only).
     * PUT /api/tablo-frontend/discussions/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255|min:3',
            'template_id' => 'nullable|integer|exists:tablo_sample_templates,id',
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

        $this->discussionService->updateDiscussion($discussion, [
            'title' => $validated['title'] ?? null,
            'tablo_sample_template_id' => $validated['template_id'] ?? $discussion->tablo_sample_template_id,
        ]);

        return $this->successResponse(null, 'Beszélgetés sikeresen frissítve!');
    }

    /**
     * Delete discussion (contact only).
     * DELETE /api/tablo-frontend/discussions/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
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

        $this->discussionService->deleteDiscussion($discussion);

        return $this->successResponse(null, 'Beszélgetés sikeresen törölve!');
    }

    /**
     * Lock discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/lock
     */
    public function lock(Request $request, int $id): JsonResponse
    {
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

        $this->discussionService->lock($discussion);

        return $this->successResponse(null, 'Beszélgetés lezárva!');
    }

    /**
     * Unlock discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/unlock
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
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

        $this->discussionService->unlock($discussion);

        return $this->successResponse(null, 'Beszélgetés feloldva!');
    }

    /**
     * Pin discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/pin
     */
    public function pin(Request $request, int $id): JsonResponse
    {
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

        $this->discussionService->pin($discussion);

        return $this->successResponse(null, 'Beszélgetés kitűzve!');
    }

    /**
     * Unpin discussion (contact only).
     * POST /api/tablo-frontend/discussions/{id}/unpin
     */
    public function unpin(Request $request, int $id): JsonResponse
    {
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

        $this->discussionService->unpin($discussion);

        return $this->successResponse(null, 'Beszélgetés levéve a kitűzésből!');
    }

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
     * Body: { "reaction": "❤️" } (optional, default: ❤️)
     * Supported reactions: 💀 😭 🫡 ❤️ 👀
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
