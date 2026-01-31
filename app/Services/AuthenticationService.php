<?php

namespace App\Services;

use App\Models\LoginAudit;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentication Service
 *
 * Handles authentication-related business logic including:
 * - Password validation and breach checking
 * - Account lockout management
 * - Session management
 * - Login auditing
 */
class AuthenticationService
{
    // Password requirements
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * Validate password strength and return validation result.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'A jelszónak legalább '.self::MIN_PASSWORD_LENGTH.' karakter hosszúnak kell lennie';
        }

        if (! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'A jelszónak tartalmaznia kell legalább egy nagybetűt';
        }

        if (! preg_match('/[a-z]/', $password)) {
            $errors[] = 'A jelszónak tartalmaznia kell legalább egy kisbetűt';
        }

        if (! preg_match('/[0-9]/', $password)) {
            $errors[] = 'A jelszónak tartalmaznia kell legalább egy számot';
        }

        if (! preg_match('/[!@#$%^&*(),.?":{}|<>\-_=+\[\]\\\\\/~`]/', $password)) {
            $errors[] = 'A jelszónak tartalmaznia kell legalább egy speciális karaktert';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if a password has been exposed in a data breach.
     * Uses the haveibeenpwned.com API with k-anonymity model.
     *
     * @return bool True if password is breached, false if safe
     */
    public function checkPasswordBreach(string $password): bool
    {
        // Check if feature is enabled
        if (! Setting::get('auth.password_breach_check', true)) {
            return false;
        }

        try {
            // SHA-1 hash of the password
            $sha1 = strtoupper(sha1($password));
            $prefix = substr($sha1, 0, 5);
            $suffix = substr($sha1, 5);

            // Query haveibeenpwned API with k-anonymity
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'PhotoStack-PasswordChecker',
                ])
                ->get("https://api.pwnedpasswords.com/range/{$prefix}");

            if (! $response->successful()) {
                Log::warning('[Auth] haveibeenpwned API request failed', [
                    'status' => $response->status(),
                ]);

                return false; // Fail open - don't block user if API is down
            }

            // Check if our suffix is in the response
            $hashes = explode("\r\n", $response->body());

            foreach ($hashes as $hash) {
                [$hashSuffix, $count] = explode(':', $hash);

                if (strtoupper($hashSuffix) === $suffix) {
                    Log::info('[Auth] Password found in breach database', [
                        'breach_count' => (int) $count,
                    ]);

                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::warning('[Auth] Password breach check failed', [
                'error' => $e->getMessage(),
            ]);

            return false; // Fail open
        }
    }

    /**
     * Increment failed login attempts for an email.
     * Returns the new count and locks account if threshold reached.
     */
    public function incrementFailedAttempts(string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return 0;
        }

        $count = $user->incrementFailedAttempts();
        $threshold = Setting::get('auth.lockout_threshold', 5);

        if ($count >= $threshold) {
            $duration = Setting::get('auth.lockout_duration_minutes', 30);
            $user->lockAccount($duration);

            Log::warning('[Auth] Account locked due to failed attempts', [
                'email' => $email,
                'failed_attempts' => $count,
                'locked_until' => $user->locked_until,
            ]);
        }

        return $count;
    }

    /**
     * Check if an account is locked.
     */
    public function isAccountLocked(string $email): bool
    {
        $user = User::where('email', $email)->first();

        return $user && $user->isLocked();
    }

    /**
     * Get lockout remaining seconds for an email.
     */
    public function getLockoutRemainingSeconds(string $email): int
    {
        $user = User::where('email', $email)->first();

        return $user ? $user->getLockoutRemainingSeconds() : 0;
    }

    /**
     * Clear failed login attempts for an email.
     */
    public function clearFailedAttempts(string $email): void
    {
        $user = User::where('email', $email)->first();

        $user?->clearFailedAttempts();
    }

    /**
     * Get active sessions (tokens) for a user.
     *
     * @return Collection<PersonalAccessToken>
     */
    public function getActiveSessions(User $user): Collection
    {
        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function (PersonalAccessToken $token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'device_name' => $token->device_name,
                    'ip_address' => $token->ip_address,
                    'login_method' => $token->login_method,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'is_current' => false, // Will be set by controller
                ];
            });
    }

    /**
     * Revoke a specific session for a user.
     */
    public function revokeSession(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            return false;
        }

        $token->delete();

        Log::info('[Auth] Session revoked', [
            'user_id' => $user->id,
            'token_id' => $tokenId,
        ]);

        return true;
    }

    /**
     * Revoke all sessions for a user except the current one.
     */
    public function revokeAllSessions(User $user, ?int $exceptTokenId = null): int
    {
        $query = $user->tokens();

        if ($exceptTokenId) {
            $query->where('id', '!=', $exceptTokenId);
        }

        $count = $query->count();
        $query->delete();

        Log::info('[Auth] All sessions revoked', [
            'user_id' => $user->id,
            'count' => $count,
            'except_token_id' => $exceptTokenId,
        ]);

        return $count;
    }

    /**
     * Enforce max sessions limit for a user.
     * Removes oldest sessions if limit exceeded.
     */
    public function enforceSessionLimit(User $user): void
    {
        $maxSessions = Setting::get('auth.max_sessions_per_user', 5);
        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        if ($tokens->count() > $maxSessions) {
            // Remove oldest tokens
            $tokensToRemove = $tokens->slice($maxSessions);

            foreach ($tokensToRemove as $token) {
                $token->delete();
            }

            Log::info('[Auth] Old sessions removed due to limit', [
                'user_id' => $user->id,
                'removed_count' => $tokensToRemove->count(),
                'max_sessions' => $maxSessions,
            ]);
        }
    }

    /**
     * Create a token with metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createTokenWithMetadata(
        User $user,
        string $name,
        string $loginMethod,
        ?string $ipAddress = null,
        ?string $deviceName = null,
        ?int $tabloProjectId = null,
        ?int $workSessionId = null,
        ?int $contactId = null
    ): string {
        $tokenResult = $user->createToken($name);
        $token = $tokenResult->accessToken;

        // Set metadata
        $token->login_method = $loginMethod;
        $token->ip_address = $ipAddress;
        $token->device_name = $deviceName;

        if ($tabloProjectId) {
            $token->tablo_project_id = $tabloProjectId;
        }

        if ($workSessionId) {
            $token->work_session_id = $workSessionId;
        }

        if ($contactId) {
            $token->contact_id = $contactId;
        }

        $token->save();

        // Enforce session limit
        $this->enforceSessionLimit($user);

        return $tokenResult->plainTextToken;
    }

    /**
     * Log a login attempt.
     */
    public function logLoginAttempt(
        ?string $email,
        string $method,
        bool $success,
        string $ipAddress,
        ?string $userAgent = null,
        ?User $user = null,
        ?string $failureReason = null,
        ?array $metadata = null
    ): LoginAudit {
        if ($success) {
            return LoginAudit::logSuccess($user, $method, $ipAddress, $userAgent, $metadata);
        }

        return LoginAudit::logFailure($email, $method, $failureReason, $ipAddress, $userAgent, $user, $metadata);
    }

    /**
     * Record a successful login.
     */
    public function recordSuccessfulLogin(User $user, string $ipAddress): void
    {
        $user->recordLogin($ipAddress);
    }

    /**
     * Check if registration is enabled.
     */
    public function isRegistrationEnabled(): bool
    {
        return Setting::get('auth.registration_enabled', false);
    }

    /**
     * Check if email verification is required.
     */
    public function isEmailVerificationRequired(): bool
    {
        return Setting::get('auth.email_verification_required', true);
    }
}
