<?php

namespace App\Events;

use App\Models\Album;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public ?Album $album = null,
        public ?Order $order = null,
    ) {}
}
