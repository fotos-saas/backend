<?php

namespace App\Events;

use App\Models\Album;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TabloUserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public WorkSession $childWorkSession,
        public Album $childAlbum,
        public WorkSession $parentWorkSession,
        public string $magicLink,
    ) {}
}
