<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?string $email,
        public string $loginMethod,
        public string $ipAddress,
        public string $failureReason,
        public ?string $userAgent = null,
        public array $metadata = []
    ) {}
}
