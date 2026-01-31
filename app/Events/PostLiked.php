<?php

namespace App\Events;

use App\Models\TabloDiscussionPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hozzászólás like-olva event
 *
 * Real-time like counter frissítés.
 */
class PostLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TabloDiscussionPost $post,
        public string $likerName,
        public bool $isLiked
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('forum.project.'.$this->post->discussion->tablo_project_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'post.liked';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->post->id,
            'discussion_id' => $this->post->tablo_discussion_id,
            'likes_count' => $this->post->likes_count,
            'liker_name' => $this->likerName,
            'is_liked' => $this->isLiked,
        ];
    }
}
