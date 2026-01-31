<?php

namespace App\Events;

use App\Models\TabloDiscussionPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hozzászólás frissítve event
 *
 * Real-time értesítés a fórum oldalon lévő felhasználóknak.
 */
class PostUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TabloDiscussionPost $post
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
        return 'post.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->id,
            'discussion_id' => $this->post->tablo_discussion_id,
            'content_preview' => \Illuminate\Support\Str::limit(strip_tags($this->post->content), 100),
            'updated_at' => $this->post->updated_at->toIso8601String(),
        ];
    }
}
