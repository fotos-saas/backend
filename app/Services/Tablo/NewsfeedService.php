<?php

namespace App\Services\Tablo;

use App\Models\TabloContact;
use App\Models\TabloGuestSession;
use App\Models\TabloNewsfeedComment;
use App\Models\TabloNewsfeedLike;
use App\Models\TabloNewsfeedMedia;
use App\Models\TabloNewsfeedPost;
use App\Models\TabloNotification;
use App\Models\TabloProject;
use App\Services\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
/**
 * Newsfeed Service
 *
 * Hírfolyam kezelés:
 * - Post CRUD (bejelentés, esemény)
 * - Kommentek
 * - Like toggle
 * - Média feltöltés
 * - Kitűzés
 */
class NewsfeedService
{
    private const MEDIA_DIRECTORY = 'tablo-newsfeed';

    private const MAX_MEDIA_COUNT = 5;

    public function __construct(
        protected PointService $pointService,
        protected BadgeService $badgeService,
        protected NotificationService $notificationService,
        protected FileStorageService $fileStorage
    ) {}

    /**
     * Projekt hírfolyam posztjai (paginated)
     *
     * @param  array  $filters  [type, search, page, per_page]
     */
    public function getPosts(TabloProject $project, array $filters = []): LengthAwarePaginator
    {
        $query = TabloNewsfeedPost::where('tablo_project_id', $project->id)
            ->with(['media'])
            ->withCount(['comments', 'likes']);

        // Típus szűrés
        if (! empty($filters['type'])) {
            if ($filters['type'] === 'announcement') {
                $query->announcements();
            } elseif ($filters['type'] === 'event') {
                $query->events();
            }
        }

        // Keresés címben és tartalomban
        if (! empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ilike', $searchTerm)
                    ->orWhere('content', 'ilike', $searchTerm);
            });
        }

        // Rendezés: pinned elöl, utána dátum szerint
        $query->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    /**
     * Közelgő események
     */
    public function getUpcomingEvents(TabloProject $project, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return TabloNewsfeedPost::where('tablo_project_id', $project->id)
            ->upcomingEvents()
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Új poszt létrehozása
     */
    public function createPost(
        TabloProject $project,
        array $data,
        string $authorType,
        int $authorId,
        array $mediaFiles = []
    ): TabloNewsfeedPost {
        return DB::transaction(function () use ($project, $data, $authorType, $authorId, $mediaFiles) {
            $post = TabloNewsfeedPost::create([
                'tablo_project_id' => $project->id,
                'author_type' => $authorType,
                'author_id' => $authorId,
                'post_type' => $data['post_type'],
                'title' => strip_tags($data['title']),
                'content' => $this->sanitizeContent($data['content'] ?? null),
                'event_date' => $data['event_date'] ?? null,
                'event_time' => $data['event_time'] ?? null,
                'event_location' => isset($data['event_location']) ? strip_tags($data['event_location']) : null,
            ]);

            // Média feltöltés
            $uploadedCount = 0;
            foreach ($mediaFiles as $file) {
                if ($uploadedCount >= self::MAX_MEDIA_COUNT) {
                    break;
                }
                $this->uploadMedia($post, $file, $uploadedCount);
                $uploadedCount++;
            }

            // Gamification pontok (csak guest-eknek)
            if ($authorType === TabloNewsfeedPost::AUTHOR_TYPE_GUEST) {
                $this->pointService->addPostPoints($authorId, $post->id);
                $this->badgeService->checkAndAwardBadges($authorId);
            }

            // Értesítés küldése a projekt tagjainak
            $this->sendNewPostNotifications($post);

            Log::info('Newsfeed post created', [
                'project_id' => $project->id,
                'post_id' => $post->id,
                'type' => $post->post_type,
            ]);

            return $post->load(['media']);
        });
    }

    /**
     * Poszt frissítése
     */
    public function updatePost(TabloNewsfeedPost $post, array $data): TabloNewsfeedPost
    {
        $updates = [];

        if (isset($data['title'])) {
            $updates['title'] = strip_tags($data['title']);
        }

        if (array_key_exists('content', $data)) {
            $updates['content'] = $this->sanitizeContent($data['content']);
        }

        if (isset($data['event_date'])) {
            $updates['event_date'] = $data['event_date'];
        }

        if (isset($data['event_time'])) {
            $updates['event_time'] = $data['event_time'];
        }

        if (isset($data['event_location'])) {
            $updates['event_location'] = strip_tags($data['event_location']);
        }

        if (! empty($updates)) {
            $post->update($updates);
        }

        Log::info('Newsfeed post updated', ['post_id' => $post->id]);

        return $post->fresh(['media']);
    }

    /**
     * Poszt frissítése + új médiák feltöltése média limit ellenőrzéssel.
     *
     * @throws \InvalidArgumentException  Ha a média limit túllépve
     */
    public function updatePostWithMedia(TabloNewsfeedPost $post, array $data, array $mediaFiles = []): TabloNewsfeedPost
    {
        $post->load('media');

        // Média limit ellenőrzés
        if (! empty($mediaFiles)) {
            $existingMediaCount = $post->media->count();
            $newMediaCount = count($mediaFiles);

            if ($existingMediaCount + $newMediaCount > self::MAX_MEDIA_COUNT) {
                $available = self::MAX_MEDIA_COUNT - $existingMediaCount;
                throw new \InvalidArgumentException(
                    "Maximum " . self::MAX_MEDIA_COUNT . " média csatolható. Jelenlegi: {$existingMediaCount}, hozzáadható: {$available}"
                );
            }
        }

        // Poszt adatok frissítése
        $post = $this->updatePost($post, $data);

        // Új médiák feltöltése
        if (! empty($mediaFiles)) {
            $currentSortOrder = $post->media->count();
            foreach ($mediaFiles as $file) {
                $this->uploadMedia($post, $file, $currentSortOrder);
                $currentSortOrder++;
            }
            $post = $post->fresh(['media']);
        }

        return $post;
    }

    /**
     * Poszt törlése
     */
    public function deletePost(TabloNewsfeedPost $post): void
    {
        $postId = $post->id;
        $projectId = $post->tablo_project_id;

        // Média fájlok törlése
        foreach ($post->media as $media) {
            $media->delete();
        }

        $post->delete();

        Log::info('Newsfeed post deleted', [
            'project_id' => $projectId,
            'post_id' => $postId,
        ]);
    }

    /**
     * Média feltöltés
     */
    public function uploadMedia(TabloNewsfeedPost $post, UploadedFile $file, int $sortOrder = 0): TabloNewsfeedMedia
    {
        $directory = self::MEDIA_DIRECTORY . '/' . $post->tablo_project_id;
        $result = $this->fileStorage->validateAndStore(
            $file,
            $directory,
            FileStorageService::imageAndVideoMimes()
        );

        return TabloNewsfeedMedia::create([
            'tablo_newsfeed_post_id' => $post->id,
            'file_path' => $result->path,
            'file_name' => $result->originalName,
            'mime_type' => $result->mimeType,
            'file_size' => $result->size,
            'is_image' => $result->isImage,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Média törlése
     */
    public function deleteMedia(TabloNewsfeedMedia $media): void
    {
        $media->delete();
        Log::info('Newsfeed media deleted', ['media_id' => $media->id]);
    }

    /**
     * Reakció toggle (emoji reakció hozzáadása/módosítása/eltávolítása)
     */
    public function toggleReaction(TabloNewsfeedPost $post, string $likerType, int $likerId, string $reaction = '❤️'): array
    {
        $existingLike = $post->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->first();

        if ($existingLike) {
            // Ha ugyanaz a reakció, töröljük
            if ($existingLike->reaction === $reaction) {
                $existingLike->delete();
                $post->updateLikesCount();

                return [
                    'liked' => false,
                    'has_reacted' => false,
                    'user_reaction' => null,
                    'reactions' => $post->getReactionsSummary(),
                    'likes_count' => $post->fresh()->likes_count,
                ];
            }

            // Ha másik reakció, cseréljük
            $existingLike->update(['reaction' => $reaction]);

            return [
                'liked' => true,
                'has_reacted' => true,
                'user_reaction' => $reaction,
                'reactions' => $post->getReactionsSummary(),
                'likes_count' => $post->likes_count,
            ];
        }

        // Új reakció létrehozása
        $like = TabloNewsfeedLike::create([
            'tablo_newsfeed_post_id' => $post->id,
            'liker_type' => $likerType,
            'liker_id' => $likerId,
            'reaction' => $reaction,
            'created_at' => now(),
        ]);

        $post->updateLikesCount();

        // Gamification (csak guest-eknek)
        if ($likerType === TabloNewsfeedLike::LIKER_TYPE_GUEST) {
            $this->pointService->addLikeGivenPoints($likerId, $like->id);

            // Like kapás pontok a post szerzőjének
            if ($post->author_type === TabloNewsfeedPost::AUTHOR_TYPE_GUEST) {
                $this->pointService->addLikeReceivedPoints($post->author_id, $like->id);
                $this->badgeService->checkAndAwardBadges($post->author_id);
            }

            $this->badgeService->checkAndAwardBadges($likerId);

            // Like notification
            $this->sendLikeNotification($post, $likerType, $likerId, $reaction);
        }

        return [
            'liked' => true,
            'has_reacted' => true,
            'user_reaction' => $reaction,
            'reactions' => $post->getReactionsSummary(),
            'likes_count' => $post->fresh()->likes_count,
        ];
    }


    /**
     * Komment létrehozása
     */
    public function createComment(
        TabloNewsfeedPost $post,
        string $content,
        string $authorType,
        int $authorId,
        ?int $parentId = null
    ): TabloNewsfeedComment {
        $comment = TabloNewsfeedComment::create([
            'tablo_newsfeed_post_id' => $post->id,
            'parent_id' => $parentId,
            'author_type' => $authorType,
            'author_id' => $authorId,
            'content' => $this->sanitizeContent($content),
        ]);

        $post->updateCommentsCount();

        // Gamification (csak guest-eknek)
        if ($authorType === TabloNewsfeedComment::AUTHOR_TYPE_GUEST) {
            $this->pointService->addReplyPoints($authorId, $comment->id);
            $this->badgeService->checkAndAwardBadges($authorId);
        }

        // Notification a poszt szerzőjének és a szülő komment szerzőjének
        $this->sendCommentNotification($post, $comment);

        Log::info('Newsfeed comment created', [
            'post_id' => $post->id,
            'comment_id' => $comment->id,
            'parent_id' => $parentId,
        ]);

        return $comment;
    }

    /**
     * Komment törlése
     */
    public function deleteComment(TabloNewsfeedComment $comment): void
    {
        $post = $comment->post;
        $commentId = $comment->id;

        $comment->delete();
        $post->updateCommentsCount();

        Log::info('Newsfeed comment deleted', ['comment_id' => $commentId]);
    }

    /**
     * Kommentek lekérése (csak top-level, válaszokkal együtt, legújabb elöl)
     */
    public function getComments(TabloNewsfeedPost $post, int $perPage = 20): LengthAwarePaginator
    {
        return $post->comments()
            ->whereNull('parent_id')
            ->with('replies')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Poszt kitűzése
     */
    public function pinPost(TabloNewsfeedPost $post): void
    {
        $post->pin();
        Log::info('Newsfeed post pinned', ['post_id' => $post->id]);
    }

    /**
     * Poszt levétele
     */
    public function unpinPost(TabloNewsfeedPost $post): void
    {
        $post->unpin();
        Log::info('Newsfeed post unpinned', ['post_id' => $post->id]);
    }

    /**
     * Content sanitization (XSS védelem)
     */
    private function sanitizeContent(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        // Alap HTML tagek engedélyezése
        $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><blockquote>';

        return strip_tags($content, $allowedTags);
    }

    /**
     * Új poszt notification küldése
     */
    private function sendNewPostNotifications(TabloNewsfeedPost $post): void
    {
        $this->notificationService->notifyProject(
            $post->project,
            TabloNotification::TYPE_NEWSFEED_POST,
            [
                'post_id' => $post->id,
                'title' => $post->title,
                'author_name' => $post->author_name,
                'post_type' => $post->post_type,
            ],
            $post->author_type,
            $post->author_id
        );
    }

    /**
     * Komment notification küldése
     */
    private function sendCommentNotification(TabloNewsfeedPost $post, TabloNewsfeedComment $comment): void
    {
        // Csak ha a poszt szerzője nem azonos a kommentelővel
        if ($post->author_type === $comment->author_type && $post->author_id === $comment->author_id) {
            return;
        }

        $this->notificationService->createNotification(
            $post->author_type,
            $post->author_id,
            TabloNotification::TYPE_NEWSFEED_COMMENT,
            [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'comment_id' => $comment->id,
                'commenter_name' => $comment->author_name,
            ],
            $post->tablo_project_id
        );
    }

    /**
     * Like notification küldése
     */
    private function sendLikeNotification(TabloNewsfeedPost $post, string $likerType, int $likerId, string $reaction = '❤️'): void
    {
        // Csak ha a poszt szerzője nem azonos a likelővel
        if ($post->author_type === $likerType && $post->author_id === $likerId) {
            return;
        }

        $likerName = 'Valaki';
        if ($likerType === TabloNewsfeedLike::LIKER_TYPE_GUEST) {
            $guestSession = TabloGuestSession::find($likerId);
            $likerName = $guestSession?->guest_name ?? 'Valaki';
        } elseif ($likerType === TabloNewsfeedLike::LIKER_TYPE_CONTACT) {
            $contact = TabloContact::find($likerId);
            $likerName = $contact?->name ?? 'Valaki';
        }

        $this->notificationService->createNotification(
            $post->author_type,
            $post->author_id,
            TabloNotification::TYPE_NEWSFEED_LIKE,
            [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'liker_name' => $likerName,
                'reaction' => $reaction,
            ],
            $post->tablo_project_id
        );
    }
}
