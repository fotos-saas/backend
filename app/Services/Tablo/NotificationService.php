<?php

namespace App\Services\Tablo;

use App\Models\TabloBadge;
use App\Models\TabloContact;
use App\Models\TabloDiscussionPost;
use App\Models\TabloGuestSession;
use App\Models\TabloNotification;
use App\Models\TabloPoke;
use App\Models\TabloPostLike;
use App\Models\TabloProject;

/**
 * Értesítési rendszer service
 */
class NotificationService
{
    /**
     * Create mention notification
     */
    public function createMentionNotification(
        TabloDiscussionPost $post,
        string $mentionedType,
        int $mentionedId
    ): void {
        // Ne küldj értesítést önmagának
        if ($post->author_type === $mentionedType && $post->author_id === $mentionedId) {
            return;
        }

        $authorName = $post->author_name ?? 'Valaki';
        $discussionTitle = $post->discussion->title ?? 'Beszélgetés';

        TabloNotification::create([
            'tablo_project_id' => $post->discussion->tablo_project_id,
            'recipient_type' => $mentionedType,
            'recipient_id' => $mentionedId,
            'type' => TabloNotification::TYPE_MENTION,
            'title' => 'Új említés egy hozzászólásban',
            'body' => "{$authorName} megemlített téged a \"{$discussionTitle}\" beszélgetésben",
            'data' => [
                'discussion_id' => $post->tablo_discussion_id,
                'post_id' => $post->id,
                'author_name' => $authorName,
                'discussion_title' => $discussionTitle,
            ],
            'notifiable_type' => TabloDiscussionPost::class,
            'notifiable_id' => $post->id,
            'action_url' => "/forum/{$post->discussion->slug}#post-{$post->id}",
        ]);
    }

    /**
     * Create reply notification
     */
    public function createReplyNotification(
        TabloDiscussionPost $reply,
        TabloDiscussionPost $parentPost
    ): void {
        // Ne küldj értesítést önmagának
        if ($reply->author_type === $parentPost->author_type && $reply->author_id === $parentPost->author_id) {
            return;
        }

        $authorName = $reply->author_name ?? 'Valaki';
        $discussionTitle = $reply->discussion->title ?? 'Beszélgetés';

        TabloNotification::create([
            'tablo_project_id' => $reply->discussion->tablo_project_id,
            'recipient_type' => $parentPost->author_type,
            'recipient_id' => $parentPost->author_id,
            'type' => TabloNotification::TYPE_REPLY,
            'title' => 'Új válasz a hozzászólásodra',
            'body' => "{$authorName} válaszolt a hozzászólásodra a \"{$discussionTitle}\" beszélgetésben",
            'data' => [
                'discussion_id' => $reply->tablo_discussion_id,
                'reply_id' => $reply->id,
                'parent_post_id' => $parentPost->id,
                'author_name' => $authorName,
                'discussion_title' => $discussionTitle,
            ],
            'notifiable_type' => TabloDiscussionPost::class,
            'notifiable_id' => $reply->id,
            'action_url' => "/forum/{$reply->discussion->slug}#post-{$reply->id}",
        ]);
    }

    /**
     * Create like notification
     */
    public function createLikeNotification(TabloPostLike $like): void
    {
        $post = $like->post;

        // Ne küldj értesítést önmagának
        if ($like->liker_type === $post->author_type && $like->liker_id === $post->author_id) {
            return;
        }

        $likerName = $like->liker_name ?? 'Valaki';
        $discussionTitle = $post->discussion->title ?? 'Beszélgetés';

        TabloNotification::create([
            'tablo_project_id' => $post->discussion->tablo_project_id,
            'recipient_type' => $post->author_type,
            'recipient_id' => $post->author_id,
            'type' => TabloNotification::TYPE_LIKE,
            'title' => 'Valaki kedvelte a hozzászólásodat',
            'body' => "{$likerName} kedvelte a hozzászólásodat a \"{$discussionTitle}\" beszélgetésben",
            'data' => [
                'discussion_id' => $post->tablo_discussion_id,
                'post_id' => $post->id,
                'liker_name' => $likerName,
                'discussion_title' => $discussionTitle,
            ],
            'notifiable_type' => TabloPostLike::class,
            'notifiable_id' => $like->id,
            'action_url' => "/forum/{$post->discussion->slug}#post-{$post->id}",
        ]);
    }

    /**
     * Create badge notification
     */
    public function createBadgeNotification(
        int $projectId,
        string $recipientType,
        int $recipientId,
        TabloBadge $badge
    ): void {
        TabloNotification::create([
            'tablo_project_id' => $projectId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => TabloNotification::TYPE_BADGE,
            'title' => 'Új kitűző szerzés!',
            'body' => "Gratulálunk! Megszerezted a(z) \"{$badge->name}\" kitűzőt",
            'data' => [
                'badge_id' => $badge->id,
                'badge_name' => $badge->name,
                'badge_tier' => $badge->tier,
                'badge_icon' => $badge->icon,
            ],
            'notifiable_type' => TabloBadge::class,
            'notifiable_id' => $badge->id,
            'action_url' => '/profile/badges',
        ]);
    }

    /**
     * Create poke notification
     */
    public function createPokeNotification(TabloPoke $poke): TabloNotification
    {
        $fromName = $poke->fromSession?->guest_name ?? 'Valaki';
        $message = $poke->text ?? $poke->custom_message ?? 'bökött téged';
        $emoji = $poke->emoji ?? '👆';

        $notification = TabloNotification::create([
            'tablo_project_id' => $poke->tablo_project_id,
            'recipient_type' => TabloNotification::RECIPIENT_TYPE_GUEST,
            'recipient_id' => $poke->target_guest_session_id,
            'type' => 'poke',
            'title' => "{$emoji} Bökés érkezett!",
            'body' => "{$fromName}: {$message}",
            'data' => [
                'poke_id' => $poke->id,
                'from_session_id' => $poke->from_guest_session_id,
                'from_name' => $fromName,
                'emoji' => $emoji,
                'message' => $message,
                'category' => $poke->category,
            ],
            'notifiable_type' => TabloPoke::class,
            'notifiable_id' => $poke->id,
            'action_url' => '/pokes',
        ]);

        // Observer automatically broadcasts NewNotification event

        return $notification;
    }

    /**
     * Create poke reaction notification
     *
     * Ha a bökés küldője is_coordinator (contact-ként lépett be),
     * akkor a contact csatornára küldjük az értesítést.
     */
    public function createPokeReactionNotification(TabloPoke $poke): TabloNotification
    {
        $reactorName = $poke->targetSession?->guest_name ?? 'Valaki';
        $reaction = $poke->reaction ?? '👍';

        // Meghatározzuk a címzett típusát
        // Ha a bökés küldője is_coordinator, akkor contact csatornára küldünk
        $fromSession = $poke->fromSession;
        $recipientType = TabloNotification::RECIPIENT_TYPE_GUEST;
        $recipientId = $poke->from_guest_session_id;

        if ($fromSession && $fromSession->is_coordinator) {
            // Contact-ként lépett be - keressük meg a projekthez tartozó contact-ot
            $project = $poke->project;
            if ($project) {
                $contact = $project->contacts()->first();
                if ($contact) {
                    $recipientType = TabloNotification::RECIPIENT_TYPE_CONTACT;
                    $recipientId = $contact->id;
                }
            }
        }

        $notification = TabloNotification::create([
            'tablo_project_id' => $poke->tablo_project_id,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => 'poke_reaction',
            'title' => "{$reaction} Reakció a bökésedre!",
            'body' => "{$reactorName} reagált a bökésedre",
            'data' => [
                'poke_id' => $poke->id,
                'reactor_session_id' => $poke->target_guest_session_id,
                'reactor_name' => $reactorName,
                'reaction' => $reaction,
            ],
            'notifiable_type' => TabloPoke::class,
            'notifiable_id' => $poke->id,
            'action_url' => '/pokes',
        ]);

        // Observer automatically broadcasts NewNotification event

        return $notification;
    }

    /**
     * Create generic notification
     */
    public function createNotification(
        string $recipientType,
        int $recipientId,
        string $type,
        array $data,
        int $projectId,
        ?string $actionUrl = null
    ): TabloNotification {
        $title = $this->getNotificationTitle($type, $data);
        $body = $this->getNotificationBody($type, $data);

        return TabloNotification::create([
            'tablo_project_id' => $projectId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'action_url' => $actionUrl ?? $this->getDefaultActionUrl($type, $data),
        ]);
    }

    /**
     * Notify all project members (contacts + guests)
     */
    public function notifyProject(
        TabloProject $project,
        string $type,
        array $data,
        ?string $excludeType = null,
        ?int $excludeId = null
    ): void {
        // Notify contacts
        foreach ($project->contacts as $contact) {
            if ($excludeType === TabloNotification::RECIPIENT_TYPE_CONTACT && $excludeId === $contact->id) {
                continue;
            }
            $this->createNotification(
                TabloNotification::RECIPIENT_TYPE_CONTACT,
                $contact->id,
                $type,
                $data,
                $project->id
            );
        }

        // Notify active guest sessions
        $guestSessions = TabloGuestSession::where('tablo_project_id', $project->id)
            ->whereNotNull('guest_name')
            ->get();

        foreach ($guestSessions as $guest) {
            if ($excludeType === TabloNotification::RECIPIENT_TYPE_GUEST && $excludeId === $guest->id) {
                continue;
            }
            $this->createNotification(
                TabloNotification::RECIPIENT_TYPE_GUEST,
                $guest->id,
                $type,
                $data,
                $project->id
            );
        }
    }

    /**
     * Get notification title based on type
     */
    private function getNotificationTitle(string $type, array $data): string
    {
        return match ($type) {
            TabloNotification::TYPE_NEWSFEED_POST => $data['post_type'] === 'event'
                ? 'Új esemény a hírfolyamban'
                : 'Új bejegyzés a hírfolyamban',
            TabloNotification::TYPE_NEWSFEED_COMMENT => 'Új hozzászólás a bejegyzésedhez',
            TabloNotification::TYPE_NEWSFEED_LIKE => 'Valaki kedvelte a bejegyzésedet',
            TabloNotification::TYPE_NEWSFEED_EVENT => 'Esemény emlékeztető',
            default => 'Új értesítés',
        };
    }

    /**
     * Get notification body based on type
     */
    private function getNotificationBody(string $type, array $data): string
    {
        $authorName = $data['author_name'] ?? $data['commenter_name'] ?? $data['liker_name'] ?? 'Valaki';
        $title = $data['title'] ?? $data['post_title'] ?? '';

        return match ($type) {
            TabloNotification::TYPE_NEWSFEED_POST => "{$authorName} közzétett: \"{$title}\"",
            TabloNotification::TYPE_NEWSFEED_COMMENT => "{$authorName} hozzászólt: \"{$title}\"",
            TabloNotification::TYPE_NEWSFEED_LIKE => "{$authorName} kedvelte a bejegyzésedet",
            TabloNotification::TYPE_NEWSFEED_EVENT => "Esemény hamarosan: \"{$title}\"",
            default => 'Új értesítés érkezett',
        };
    }

    /**
     * Get default action URL based on type
     */
    private function getDefaultActionUrl(string $type, array $data): string
    {
        $postId = $data['post_id'] ?? null;

        return match ($type) {
            TabloNotification::TYPE_NEWSFEED_POST,
            TabloNotification::TYPE_NEWSFEED_COMMENT,
            TabloNotification::TYPE_NEWSFEED_LIKE,
            TabloNotification::TYPE_NEWSFEED_EVENT => $postId ? "/newsfeed/{$postId}" : '/newsfeed',
            default => '/notifications',
        };
    }

    /**
     * Get unread count for user/guest
     */
    public function getUnreadCount(int $projectId, string $recipientType, int $recipientId): int
    {
        return TabloNotification::where('tablo_project_id', $projectId)
            ->forRecipient($recipientType, $recipientId)
            ->unread()
            ->count();
    }

    /**
     * Mark all as read for user/guest
     */
    public function markAllAsRead(int $projectId, string $recipientType, int $recipientId): void
    {
        TabloNotification::where('tablo_project_id', $projectId)
            ->forRecipient($recipientType, $recipientId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get notifications for user/guest
     */
    public function getNotifications(
        int $projectId,
        string $recipientType,
        int $recipientId,
        int $limit = 20,
        bool $unreadOnly = false
    ): \Illuminate\Support\Collection {
        $query = TabloNotification::where('tablo_project_id', $projectId)
            ->forRecipient($recipientType, $recipientId)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->limit($limit)->get();
    }
}
