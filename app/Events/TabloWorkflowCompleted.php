<?php

namespace App\Events;

use App\Models\Photo;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TabloWorkflowCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public WorkSession $workSession,
        public Photo $selectedPhoto,
    ) {}
}
