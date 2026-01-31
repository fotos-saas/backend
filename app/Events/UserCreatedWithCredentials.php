<?php

namespace App\Events;

use App\Models\Album;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreatedWithCredentials
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public ?Album $album = null,
    ) {}
}

