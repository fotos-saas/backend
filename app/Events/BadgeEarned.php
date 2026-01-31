<?php

namespace App\Events;

use App\Models\TabloBadge;
use App\Models\TabloUserBadge;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Badge megszerzése event
 *
 * Real-time értesítés badge szerzésről.
 */
class BadgeEarned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TabloUserBadge $userBadge,
        public int $projectId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf(
                'notifications.%d.guest.%d',
                $this->projectId,
                $this->userBadge->tablo_guest_session_id
            )),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'badge.earned';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $badge = $this->userBadge->badge;

        return [
            'user_badge_id' => $this->userBadge->id,
            'badge' => [
                'id' => $badge->id,
                'key' => $badge->key,
                'name' => $badge->name,
                'description' => $badge->description,
                'tier' => $badge->tier,
                'icon' => $badge->icon,
                'color' => $badge->color,
                'points' => $badge->points,
            ],
            'earned_at' => $this->userBadge->earned_at->toIso8601String(),
        ];
    }
}
