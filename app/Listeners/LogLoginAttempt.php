<?php

namespace App\Listeners;

use App\Events\LoginFailed;
use App\Events\UserLoggedIn;
use App\Models\LoginAudit;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogLoginAttempt implements ShouldQueue
{
    /**
     * Handle UserLoggedIn events.
     */
    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        LoginAudit::create([
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'login_method' => $event->loginMethod,
            'success' => true,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'failure_reason' => null,
            'metadata' => $event->metadata,
        ]);

        // Update user's last login info
        $event->user->update([
            'last_login_at' => now(),
            'last_login_ip' => $event->ipAddress,
        ]);
    }

    /**
     * Handle LoginFailed events.
     */
    public function handleLoginFailed(LoginFailed $event): void
    {
        LoginAudit::create([
            'user_id' => null,
            'email' => $event->email,
            'login_method' => $event->loginMethod,
            'success' => false,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'failure_reason' => $event->failureReason,
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe($events): array
    {
        return [
            UserLoggedIn::class => 'handleUserLoggedIn',
            LoginFailed::class => 'handleLoginFailed',
        ];
    }
}
