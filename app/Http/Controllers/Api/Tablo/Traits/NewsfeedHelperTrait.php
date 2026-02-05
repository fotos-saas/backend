<?php

namespace App\Http\Controllers\Api\Tablo\Traits;

use App\Models\TabloNewsfeedComment;
use App\Models\TabloNewsfeedPost;
use Illuminate\Http\Request;

/**
 * Trait: NewsfeedHelperTrait
 *
 * Közös helper metódusok a NewsfeedPostController és NewsfeedCommentController számára.
 * Formázás és szerző azonosítás.
 */
trait NewsfeedHelperTrait
{
    /**
     * Get author info from request (contact or guest).
     */
    protected function getAuthorInfo(Request $request): array
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
    protected function getCurrentUserInfo(Request $request): array
    {
        return $this->getAuthorInfo($request);
    }

    /**
     * Format post for API response.
     */
    protected function formatPost(TabloNewsfeedPost $post, ?string $currentUserType, ?int $currentUserId): array
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
    protected function formatPostWithComments(TabloNewsfeedPost $post, ?string $currentUserType, ?int $currentUserId): array
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
    protected function formatComment(TabloNewsfeedComment $comment, ?string $currentUserType, ?int $currentUserId, bool $includeReplies = true): array
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
