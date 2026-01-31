<?php

namespace App\Services\Tablo;

use App\Events\NewPostCreated;
use App\Events\PostDeleted;
use App\Events\PostLiked;
use App\Events\PostUpdated;
use App\Models\TabloContact;
use App\Models\TabloDiscussion;
use App\Models\TabloDiscussionPost;
use App\Models\TabloGuestSession;
use App\Models\TabloPostMedia;
use App\Models\TabloProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Discussion Service
 *
 * Fórum/beszélgetés kezelés:
 * - Discussion CRUD
 * - Hozzászólások kezelése
 * - Like toggle
 * - Média feltöltés
 * - Moderálás
 * - Gamification integráció (pontok, badge-ek)
 * - Real-time broadcast
 */
class DiscussionService
{
    /**
     * Média feltöltés könyvtár
     */
    private const MEDIA_DIRECTORY = 'tablo-discussions';

    /**
     * Max média méret (5MB)
     */
    private const MAX_MEDIA_SIZE = 5 * 1024 * 1024;

    public function __construct(
        protected PointService $pointService,
        protected BadgeService $badgeService,
        protected NotificationService $notificationService,
        protected LeaderboardService $leaderboardService,
        protected TabloMediaService $mediaService
    ) {}

    /**
     * Új beszélgetés létrehozása
     */
    public function createDiscussion(
        TabloProject $project,
        string $title,
        string $content,
        string $creatorType,
        int $creatorId,
        ?int $templateId = null
    ): TabloDiscussion {
        return DB::transaction(function () use ($project, $title, $content, $creatorType, $creatorId, $templateId) {
            $discussion = TabloDiscussion::create([
                'tablo_project_id' => $project->id,
                'tablo_sample_template_id' => $templateId,
                'creator_type' => $creatorType,
                'creator_id' => $creatorId,
                'title' => $title,
                'slug' => TabloDiscussion::generateUniqueSlug($title),
            ]);

            // Első hozzászólás (a topic body)
            TabloDiscussionPost::create([
                'tablo_discussion_id' => $discussion->id,
                'author_type' => $creatorType,
                'author_id' => $creatorId,
                'content' => $content,
                'mentions' => TabloDiscussionPost::parseMentions($content),
            ]);

            $discussion->updatePostsCount();

            Log::info('Discussion created', [
                'project_id' => $project->id,
                'discussion_id' => $discussion->id,
            ]);

            return $discussion;
        });
    }

    /**
     * Beszélgetés frissítése
     */
    public function updateDiscussion(TabloDiscussion $discussion, array $data): TabloDiscussion
    {
        $updates = [];

        if (isset($data['title'])) {
            $updates['title'] = $data['title'];
            // Slug csak akkor változik, ha explicit kérik
            if ($data['update_slug'] ?? false) {
                $updates['slug'] = TabloDiscussion::generateUniqueSlug($data['title']);
            }
        }

        if (isset($data['tablo_sample_template_id'])) {
            $updates['tablo_sample_template_id'] = $data['tablo_sample_template_id'];
        }

        if (! empty($updates)) {
            $discussion->update($updates);
        }

        return $discussion->fresh();
    }

    /**
     * Beszélgetés törlése
     */
    public function deleteDiscussion(TabloDiscussion $discussion): void
    {
        $discussionId = $discussion->id;
        $projectId = $discussion->tablo_project_id;

        // Média fájlok törlése
        foreach ($discussion->posts as $post) {
            foreach ($post->media as $media) {
                $media->delete(); // Ez törli a fájlt is
            }
        }

        $discussion->delete();

        Log::info('Discussion deleted', [
            'project_id' => $projectId,
            'discussion_id' => $discussionId,
        ]);
    }

    /**
     * Új hozzászólás
     */
    public function createPost(
        TabloDiscussion $discussion,
        string $content,
        string $authorType,
        int $authorId,
        ?int $parentId = null,
        array $mediaFiles = []
    ): TabloDiscussionPost {
        if ($discussion->is_locked) {
            throw new \InvalidArgumentException('A beszélgetés le van zárva.');
        }

        return DB::transaction(function () use ($discussion, $content, $authorType, $authorId, $parentId, $mediaFiles) {
            $post = TabloDiscussionPost::create([
                'tablo_discussion_id' => $discussion->id,
                'parent_id' => $parentId,
                'author_type' => $authorType,
                'author_id' => $authorId,
                'content' => $content,
                'mentions' => TabloDiscussionPost::parseMentions($content),
            ]);

            // Média fájlok feltöltése
            foreach ($mediaFiles as $file) {
                $this->uploadMedia($post, $file);
            }

            // Gamification: pontok és badge-ek (csak guest-eknek)
            if ($authorType === TabloDiscussionPost::AUTHOR_TYPE_GUEST) {
                $isReply = $parentId !== null;

                if ($isReply) {
                    $this->pointService->addReplyPoints($authorId, $post->id);

                    // Reply notification a parent post szerzőjének
                    $parentPost = TabloDiscussionPost::find($parentId);
                    if ($parentPost) {
                        $this->notificationService->createReplyNotification($post, $parentPost);
                    }
                } else {
                    $this->pointService->addPostPoints($authorId, $post->id);
                }

                // Mention notificationök
                $mentions = $post->mentions ?? [];
                foreach ($mentions as $mention) {
                    $this->notificationService->createMentionNotification(
                        $post,
                        $mention['type'],
                        $mention['id']
                    );
                }

                // Badge ellenőrzés
                $this->badgeService->checkAndAwardBadges($authorId);

                // Leaderboard cache törlése
                $this->leaderboardService->clearCache($discussion->tablo_project_id);
            }

            // Broadcast event
            broadcast(new NewPostCreated($post))->toOthers();

            Log::info('Post created', [
                'discussion_id' => $discussion->id,
                'post_id' => $post->id,
                'is_reply' => $parentId !== null,
            ]);

            return $post;
        });
    }

    /**
     * Hozzászólás szerkesztése
     *
     * @param bool $byModerator Ha true, az időkorlát ellenőrzése kihagyásra kerül (contact/admin)
     */
    public function updatePost(TabloDiscussionPost $post, string $content, bool $byModerator = false): TabloDiscussionPost
    {
        // Moderátor/contact bármikor szerkeszthet, guest csak 15 percen belül
        if (! $byModerator && ! $post->canEdit()) {
            throw new \InvalidArgumentException('A szerkesztési idő lejárt.');
        }

        $post->editContent($content);

        // Broadcast event
        broadcast(new PostUpdated($post))->toOthers();

        Log::info('Post updated', ['post_id' => $post->id]);

        return $post;
    }

    /**
     * Hozzászólás törlése
     */
    public function deletePost(TabloDiscussionPost $post, string $deletedBy = 'moderator'): void
    {
        $postId = $post->id;
        $discussionId = $post->tablo_discussion_id;
        $projectId = $post->discussion->tablo_project_id;

        // Média törlése
        foreach ($post->media as $media) {
            $media->delete();
        }

        $post->delete();

        // Frissítjük a discussion posts_count-ot
        TabloDiscussion::find($discussionId)?->updatePostsCount();

        // Broadcast event
        broadcast(new PostDeleted($postId, $discussionId, $projectId, $deletedBy))->toOthers();

        Log::info('Post deleted', [
            'discussion_id' => $discussionId,
            'post_id' => $postId,
        ]);
    }

    /**
     * Toggle reaction (emoji reakció hozzáadása/eltávolítása/módosítása)
     *
     * @return array{added: bool, reaction: string|null, reactions: array, likesCount: int}
     */
    public function toggleReaction(
        TabloDiscussionPost $post,
        string $likerType,
        int $likerId,
        string $reaction = '❤️'
    ): array {
        $result = $post->toggleReaction($likerType, $likerId, $reaction);

        // Get liker name for broadcast
        $likerName = 'Valaki';
        if ($likerType === TabloDiscussionPost::AUTHOR_TYPE_GUEST) {
            $guestSession = TabloGuestSession::find($likerId);
            $likerName = $guestSession?->guest_name ?? 'Valaki';
        }

        // Gamification: pontok reakcióért (csak guest-eknek, csak új reakció esetén)
        if ($likerType === TabloDiscussionPost::AUTHOR_TYPE_GUEST && $result['added'] && !$result['oldReaction']) {
            $projectId = $post->discussion->tablo_project_id;

            $like = $post->likes()->where('liker_type', $likerType)->where('liker_id', $likerId)->first();
            if ($like) {
                $this->pointService->addLikeGivenPoints($likerId, $like->id);

                // Like kapás pontok a post szerzőjének
                if ($post->author_type === TabloDiscussionPost::AUTHOR_TYPE_GUEST) {
                    $this->pointService->addLikeReceivedPoints($post->author_id, $like->id);

                    // Notification
                    $this->notificationService->createLikeNotification($like);

                    // Badge ellenőrzés a post szerzőjénél
                    $this->badgeService->checkAndAwardBadges($post->author_id);
                }

                // Badge ellenőrzés a liker-nél
                $this->badgeService->checkAndAwardBadges($likerId);

                // Leaderboard cache törlése
                $this->leaderboardService->clearCache($projectId);
            }
        }

        // Broadcast event
        broadcast(new PostLiked($post->fresh(), $likerName, $result['added']))->toOthers();

        return [
            'added' => $result['added'],
            'reaction' => $result['reaction'],
            'reactions' => $post->getReactionsSummary(),
            'likesCount' => $post->fresh()->likes_count,
        ];
    }

    /**
     * Like toggle (legacy - ❤️ reakció)
     * @deprecated Use toggleReaction() instead
     */
    public function toggleLike(TabloDiscussionPost $post, string $likerType, int $likerId): bool
    {
        $result = $this->toggleReaction($post, $likerType, $likerId, '❤️');

        return $result['added'];
    }

    /**
     * Média feltöltés
     */
    public function uploadMedia(TabloDiscussionPost $post, UploadedFile $file): TabloPostMedia
    {
        $stored = $this->mediaService->validateAndStore(
            $file,
            self::MEDIA_DIRECTORY,
            $post->discussion->tablo_project_id,
            TabloMediaService::DEFAULT_ALLOWED_MIMES,
            self::MAX_MEDIA_SIZE
        );

        return TabloPostMedia::create([
            'tablo_discussion_post_id' => $post->id,
            'file_path' => $stored['path'],
            'file_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['size'],
        ]);
    }

    /**
     * Média törlés adott post-ból
     *
     * @param TabloDiscussionPost $post
     * @param array<int> $mediaIds
     */
    public function deletePostMedia(TabloDiscussionPost $post, array $mediaIds): void
    {
        // Convert string IDs to integers (FormData sends strings)
        $mediaIds = array_map('intval', $mediaIds);

        foreach ($post->media as $media) {
            if (in_array($media->id, $mediaIds, true)) {
                $media->delete(); // Ez törli a fájlt is
                Log::info('Post media deleted', [
                    'post_id' => $post->id,
                    'media_id' => $media->id,
                ]);
            }
        }
    }

    /**
     * Beszélgetés lezárása
     */
    public function lock(TabloDiscussion $discussion): void
    {
        $discussion->lock();
        Log::info('Discussion locked', ['discussion_id' => $discussion->id]);
    }

    /**
     * Beszélgetés feloldása
     */
    public function unlock(TabloDiscussion $discussion): void
    {
        $discussion->unlock();
        Log::info('Discussion unlocked', ['discussion_id' => $discussion->id]);
    }

    /**
     * Beszélgetés kitűzése
     */
    public function pin(TabloDiscussion $discussion): void
    {
        $discussion->pin();
        Log::info('Discussion pinned', ['discussion_id' => $discussion->id]);
    }

    /**
     * Beszélgetés levétele
     */
    public function unpin(TabloDiscussion $discussion): void
    {
        $discussion->unpin();
        Log::info('Discussion unpinned', ['discussion_id' => $discussion->id]);
    }

    /**
     * Projekt beszélgetései szűréssel
     *
     * @param array $filters [search, template_id, sort_by]
     */
    public function getByProject(TabloProject $project, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $project->discussions()
            ->withCount('posts')
            ->with(['template']);

        // Keresés cím és első post tartalmában
        if (! empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ilike', $searchTerm)
                  ->orWhereHas('posts', function ($postQuery) use ($searchTerm) {
                      $postQuery->where('content', 'ilike', $searchTerm);
                  });
            });
        }

        // Sablon szűrés
        if (! empty($filters['template_id'])) {
            $query->where('tablo_sample_template_id', $filters['template_id']);
        }

        // Rendezés
        $sortBy = $filters['sort_by'] ?? 'latest';
        switch ($sortBy) {
            case 'oldest':
                $query->orderByDesc('is_pinned')->orderBy('created_at');
                break;
            case 'most_posts':
                $query->orderByDesc('is_pinned')->orderByDesc('posts_count');
                break;
            case 'most_views':
                $query->orderByDesc('is_pinned')->orderByDesc('views_count');
                break;
            case 'latest':
            default:
                $query->orderByDesc('is_pinned')->orderByDesc('updated_at');
                break;
        }

        return $query->get();
    }

    /**
     * Beszélgetés részletek hozzászólásokkal
     */
    public function getWithPosts(TabloDiscussion $discussion, int $perPage = 20): array
    {
        // Nézet számláló növelése
        $discussion->incrementViews();

        $rootPosts = $discussion->rootPosts()
            ->with(['replies.likes', 'media', 'likes'])
            ->paginate($perPage);

        return [
            'discussion' => $discussion->load(['template']),
            'posts' => $rootPosts,
        ];
    }

    /**
     * Sablon beszélgetései
     */
    public function getByTemplate(TabloProject $project, int $templateId): \Illuminate\Database\Eloquent\Collection
    {
        return $project->discussions()
            ->where('tablo_sample_template_id', $templateId)
            ->orderedByActivity()
            ->get();
    }

    /**
     * Ellenőrzi, hogy a felhasználó (contact/guest) szerkesztheti-e a postot
     */
    public function canUserEditPost(TabloDiscussionPost $post, string $userType, int $userId): bool
    {
        // Csak a szerző szerkesztheti
        if ($post->author_type !== $userType || $post->author_id !== $userId) {
            return false;
        }

        return $post->canEdit();
    }

    /**
     * Ellenőrzi, hogy a felhasználó törölheti-e a postot
     */
    public function canUserDeletePost(
        TabloDiscussionPost $post,
        string $userType,
        int $userId,
        bool $isModerator = false
    ): bool {
        // Moderátor bármit törölhet
        if ($isModerator) {
            return true;
        }

        // Szerző törölheti a sajátját
        return $post->author_type === $userType && $post->author_id === $userId;
    }
}
