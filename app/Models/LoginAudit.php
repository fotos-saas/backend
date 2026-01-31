<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Login Audit model for tracking all login attempts.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $login_method password|code|magic_link|qr_registration
 * @property bool $success
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $failure_reason
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class LoginAudit extends Model
{
    use HasFactory;

    // Login methods
    public const METHOD_PASSWORD = 'password';

    public const METHOD_CODE = 'code';

    public const METHOD_MAGIC_LINK = 'magic_link';

    public const METHOD_QR_REGISTRATION = 'qr_registration';

    // Failure reasons
    public const FAILURE_INVALID_CREDENTIALS = 'invalid_credentials';

    public const FAILURE_ACCOUNT_LOCKED = 'account_locked';

    public const FAILURE_EMAIL_NOT_VERIFIED = 'email_not_verified';

    public const FAILURE_INVALID_CODE = 'invalid_code';

    public const FAILURE_EXPIRED_CODE = 'expired_code';

    public const FAILURE_EXPIRED_TOKEN = 'expired_token';

    public const FAILURE_INVALID_TOKEN = 'invalid_token';

    public const FAILURE_BREACHED_PASSWORD = 'breached_password';

    public const FAILURE_RATE_LIMITED = 'rate_limited';

    protected $fillable = [
        'user_id',
        'email',
        'login_method',
        'success',
        'ip_address',
        'user_agent',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user this audit belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a successful login attempt.
     */
    public static function logSuccess(
        User $user,
        string $method,
        string $ipAddress,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'login_method' => $method,
            'success' => true,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailure(
        ?string $email,
        string $method,
        string $failureReason,
        string $ipAddress,
        ?string $userAgent = null,
        ?User $user = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'user_id' => $user?->id,
            'email' => $email,
            'login_method' => $method,
            'success' => false,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'failure_reason' => $failureReason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get recent failed attempts for an email.
     */
    public static function getRecentFailedAttempts(string $email, int $minutes = 30): int
    {
        return static::where('email', $email)
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Get recent failed attempts for an IP.
     */
    public static function getRecentFailedAttemptsFromIp(string $ipAddress, int $minutes = 30): int
    {
        return static::where('ip_address', $ipAddress)
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Scope for successful logins.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope for failed logins.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope by login method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('login_method', $method);
    }

    /**
     * Scope for recent entries.
     */
    public function scopeRecent($query, int $minutes = 30)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
