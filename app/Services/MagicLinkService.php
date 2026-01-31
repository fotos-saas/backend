<?php

namespace App\Services;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Generate a magic link token for a user
     *
     * @param  User  $user  The user to generate the token for
     * @param  int  $expirationHours  How many hours the token should be valid
     * @return array{token: string, url: string, expires_at: \Carbon\Carbon}
     */
    public function generate(User $user, int $expirationHours = 24): array
    {
        // Generate unique token
        $token = $this->generateUniqueToken();

        // Create magic login token record
        $magicToken = MagicLoginToken::create([
            'token' => $token,
            'user_id' => $user->id,
            'expires_at' => now()->addHours($expirationHours),
        ]);

        // Build magic link URL
        $url = config('app.frontend_url', config('app.url')).'/auth/magic/'.$token;

        return [
            'token' => $token,
            'url' => $url,
            'expires_at' => $magicToken->expires_at,
        ];
    }

    /**
     * Generate a magic link token for a user with work session context
     *
     * @param  User  $user  The user to generate the token for
     * @param  \App\Models\WorkSession  $workSession  The work session context
     * @param  int  $expirationDays  How many days the token should be valid
     * @return array{token: string, url: string, expires_at: \Carbon\Carbon}
     */
    public function generateForWorkSession(User $user, \App\Models\WorkSession $workSession, int $expirationDays = 30): array
    {
        // Generate unique token
        $token = $this->generateUniqueToken();

        // Create magic login token record
        $magicToken = MagicLoginToken::create([
            'token' => $token,
            'user_id' => $user->id,
            'work_session_id' => $workSession->id,
            'expires_at' => now()->addDays($expirationDays),
        ]);

        // Build magic link URL
        $url = config('app.frontend_url', config('app.url')).'/auth/magic/'.$token;

        return [
            'token' => $token,
            'url' => $url,
            'expires_at' => $magicToken->expires_at,
        ];
    }

    /**
     * Validate and get magic token data (but don't consume it yet)
     *
     * @param  string  $token  The magic link token
     * @return MagicLoginToken|null The token if valid, null otherwise
     */
    public function validateToken(string $token): ?MagicLoginToken
    {
        $magicToken = MagicLoginToken::with(['user', 'workSession'])
            ->where('token', $token)
            ->first();

        if (! $magicToken || $magicToken->isExpired()) {
            return null;
        }

        return $magicToken;
    }

    /**
     * Consume a magic link token and return the associated user
     * Note: For work session tokens, this doesn't mark as used (reusable)
     *
     * @param  string  $token  The magic link token
     * @return User|null The user if token is valid, null otherwise
     */
    public function consume(string $token): ?User
    {
        $magicToken = $this->validateToken($token);

        if (! $magicToken) {
            return null;
        }

        // Only mark as used if it's NOT a work session token (those are reusable)
        if (! $magicToken->work_session_id) {
            $magicToken->markAsUsed();
        }

        return $magicToken->user;
    }

    /**
     * Clean up expired and used tokens
     *
     * @return int Number of deleted tokens
     */
    public function cleanup(): int
    {
        return MagicLoginToken::where(function ($query) {
            $query->where('expires_at', '<', now())
                ->orWhereNotNull('used_at');
        })
            ->where('created_at', '<', now()->subDays(7)) // Keep recent tokens for 7 days for logging
            ->delete();
    }

    /**
     * Revoke all magic tokens for a user
     *
     * @param  User  $user  The user whose tokens should be revoked
     */
    public function revokeUserTokens(User $user): void
    {
        MagicLoginToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    /**
     * Generate a unique token string
     */
    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (MagicLoginToken::where('token', $token)->exists());

        return $token;
    }
}
