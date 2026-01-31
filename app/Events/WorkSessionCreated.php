<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkSessionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WorkSession $workSession,
        public ?User $user = null,
    ) {}
}

