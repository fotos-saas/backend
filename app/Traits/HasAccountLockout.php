<?php

namespace App\Traits;

/**
 * Account lockout (brute force vedelem) logika.
 *
 * A User model-hez tartozo bejelentkezesi biztonsagi metodusok.
 */
trait HasAccountLockout
{
    /**
     * Check if the account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Get remaining lockout time in seconds.
     */
    public function getLockoutRemainingSeconds(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return (int) now()->diffInSeconds($this->locked_until, false);
    }

    /**
     * Increment failed login attempts.
     */
    public function incrementFailedAttempts(): int
    {
        $this->increment('failed_login_attempts');

        return $this->failed_login_attempts;
    }

    /**
     * Lock the account for the specified duration.
     */
    public function lockAccount(int $minutes): void
    {
        $this->update(['locked_until' => now()->addMinutes($minutes)]);
    }

    /**
     * Clear failed login attempts and unlock the account.
     */
    public function clearFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Record a successful login.
     */
    public function recordLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
