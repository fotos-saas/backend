<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class SmtpAccount extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'mailer_type',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'rate_limit_per_minute',
        'rate_limit_per_hour',
        'priority',
        'is_prod',
        'is_active',
        'health_status',
        'last_health_check_at',
        'health_error_message',
        'manual_redirect_to',
        'dkim_domain',
        'dkim_selector',
        'dkim_private_key',
        'dmarc_policy',
        'spf_record',
        'bounce_email',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_sent_folder',
        'imap_save_sent',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'port' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_hour' => 'integer',
        'priority' => 'integer',
        'is_prod' => 'boolean',
        'is_active' => 'boolean',
        'last_health_check_at' => 'datetime',
        'imap_port' => 'integer',
        'imap_save_sent' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'dkim_private_key',
        'imap_password',
    ];

    /**
     * Boot method - ensure only one active SMTP per environment
     */
    protected static function booted(): void
    {
        static::saved(function (self $account): void {
            if ($account->is_active) {
                // Deactivate other accounts in same environment
                static::query()
                    ->where('is_prod', $account->is_prod)
                    ->whereKeyNot($account->getKey())
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Relationship: Email logs sent via this SMTP account
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Relationship: Email statistics for this SMTP account
     */
    public function emailStatistics(): HasMany
    {
        return $this->hasMany(EmailStatistic::class);
    }

    /**
     * Relationship: Manual redirect target SMTP account
     */
    public function redirectTarget(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class, 'manual_redirect_to');
    }

    /**
     * Relationship: Accounts that redirect to this one
     */
    public function redirectingSources(): HasMany
    {
        return $this->hasMany(SmtpAccount::class, 'manual_redirect_to');
    }

    /**
     * Scope: Production SMTP accounts only
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProd($query)
    {
        return $query->where('is_prod', true);
    }

    /**
     * Scope: Development SMTP accounts only
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDev($query)
    {
        return $query->where('is_prod', false);
    }

    /**
     * Scope: Active SMTP accounts only
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Healthy SMTP accounts only
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    /**
     * Get dynamic mailer name for Laravel Mail configuration
     */
    public function getDynamicMailerName(): string
    {
        $mailerName = "smtp_{$this->id}";

        // Configure mailer dynamically
        Config::set("mail.mailers.{$mailerName}", [
            'transport' => $this->mailer_type,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption,
            'timeout' => null,
        ]);

        Config::set('mail.from', [
            'address' => $this->from_address,
            'name' => $this->from_name,
        ]);

        return $mailerName;
    }

    /**
     * Get the actual SMTP account to use (considering manual redirects)
     */
    public function getEffectiveAccount(): self
    {
        if ($this->manual_redirect_to) {
            return $this->redirectTarget;
        }

        return $this;
    }

    /**
     * Check if this account is within rate limits
     */
    public function isWithinRateLimit(): bool
    {
        $now = now();

        // Check per-minute limit
        if ($this->rate_limit_per_minute) {
            $sentLastMinute = $this->emailLogs()
                ->where('created_at', '>=', $now->copy()->subMinute())
                ->count();

            if ($sentLastMinute >= $this->rate_limit_per_minute) {
                return false;
            }
        }

        // Check per-hour limit
        if ($this->rate_limit_per_hour) {
            $sentLastHour = $this->emailLogs()
                ->where('created_at', '>=', $now->copy()->subHour())
                ->count();

            if ($sentLastHour >= $this->rate_limit_per_hour) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get rate limit usage percentage (0-100)
     */
    public function getRateLimitUsagePercentage(): int
    {
        if (! $this->rate_limit_per_minute && ! $this->rate_limit_per_hour) {
            return 0;
        }

        $now = now();
        $maxPercentage = 0;

        // Check per-minute usage
        if ($this->rate_limit_per_minute) {
            $sentLastMinute = $this->emailLogs()
                ->where('created_at', '>=', $now->copy()->subMinute())
                ->count();

            $minutePercentage = ($sentLastMinute / $this->rate_limit_per_minute) * 100;
            $maxPercentage = max($maxPercentage, $minutePercentage);
        }

        // Check per-hour usage
        if ($this->rate_limit_per_hour) {
            $sentLastHour = $this->emailLogs()
                ->where('created_at', '>=', $now->copy()->subHour())
                ->count();

            $hourPercentage = ($sentLastHour / $this->rate_limit_per_hour) * 100;
            $maxPercentage = max($maxPercentage, $hourPercentage);
        }

        return (int) min(100, $maxPercentage);
    }

    /**
     * Check if IMAP save to sent is enabled and configured
     */
    public function canSaveToSent(): bool
    {
        return $this->imap_save_sent
            && $this->imap_host
            && $this->imap_username
            && $this->imap_password;
    }

    /**
     * Save email to IMAP Sent folder.
     * Delegates to ImapSentFolderService.
     *
     * @param  array  $attachments  Array of ['content' => string, 'filename' => string, 'mime' => string]
     */
    public function saveToSentFolder(string $to, string $subject, string $body, array $headers = [], array $attachments = []): bool
    {
        return app(\App\Services\ImapSentFolderService::class)
            ->saveToSentFolder($this, $to, $subject, $body, $headers, $attachments);
    }
}
