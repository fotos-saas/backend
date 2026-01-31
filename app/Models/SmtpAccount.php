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
     * Save email to IMAP Sent folder using webklex/php-imap
     *
     * @param  array  $attachments  Array of ['content' => string, 'filename' => string, 'mime' => string]
     */
    public function saveToSentFolder(string $to, string $subject, string $body, array $headers = [], array $attachments = []): bool
    {
        \Log::info('IMAP saveToSentFolder called', [
            'smtp_account_id' => $this->id,
            'to' => $to,
            'subject' => $subject,
            'attachments_count' => count($attachments),
            'can_save' => $this->canSaveToSent(),
            'imap_host' => $this->imap_host,
            'imap_sent_folder' => $this->imap_sent_folder,
        ]);

        if (! $this->canSaveToSent()) {
            \Log::warning('IMAP canSaveToSent returned false', [
                'smtp_account_id' => $this->id,
                'imap_save_sent' => $this->imap_save_sent,
                'imap_host' => $this->imap_host,
                'imap_username' => $this->imap_username,
                'has_password' => ! empty($this->imap_password),
            ]);

            return false;
        }

        try {
            \Log::info('IMAP connecting...', ['smtp_account_id' => $this->id]);

            $cm = new \Webklex\PHPIMAP\ClientManager;
            $client = $cm->make([
                'host' => $this->imap_host,
                'port' => $this->imap_port,
                'encryption' => $this->imap_encryption ?: 'ssl',
                'validate_cert' => true,
                'username' => $this->imap_username,
                'password' => $this->imap_password,
                'protocol' => 'imap',
            ]);

            $client->connect();
            \Log::info('IMAP connected successfully', ['smtp_account_id' => $this->id]);

            // Build RFC 822 email message (with attachments if provided)
            $message = $this->buildRfc822Message($to, $subject, $body, $headers, $attachments);
            \Log::info('IMAP message built', [
                'smtp_account_id' => $this->id,
                'message_length' => strlen($message),
                'has_attachments' => count($attachments) > 0,
            ]);

            // Get the Sent folder
            $folder = $client->getFolderByPath($this->imap_sent_folder);

            if (! $folder) {
                \Log::error('IMAP Sent folder not found', [
                    'smtp_account_id' => $this->id,
                    'folder' => $this->imap_sent_folder,
                ]);
                $client->disconnect();

                return false;
            }

            \Log::info('IMAP folder found, appending message...', [
                'smtp_account_id' => $this->id,
                'folder' => $this->imap_sent_folder,
            ]);

            // Append message to Sent folder
            $result = $folder->appendMessage($message, ['\\Seen']);

            \Log::info('IMAP appendMessage result', [
                'smtp_account_id' => $this->id,
                'result' => $result,
                'result_type' => gettype($result),
            ]);

            $client->disconnect();

            return (bool) $result;
        } catch (\Throwable $e) {
            \Log::error('IMAP save to sent failed', [
                'smtp_account_id' => $this->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build RFC 822 formatted email message with optional attachments
     *
     * @param  array  $attachments  Array of ['content' => string, 'filename' => string, 'mime' => string]
     */
    protected function buildRfc822Message(string $to, string $subject, string $body, array $headers = [], array $attachments = []): string
    {
        $boundary = '----=_Part_'.md5(uniqid());

        $defaultHeaders = [
            'From' => "{$this->from_name} <{$this->from_address}>",
            'To' => $to,
            'Subject' => $subject,
            'Date' => date('r'),
            'MIME-Version' => '1.0',
        ];

        // Ha vannak csatolmányok, multipart/mixed kell
        if (! empty($attachments)) {
            $defaultHeaders['Content-Type'] = "multipart/mixed; boundary=\"{$boundary}\"";
        } else {
            $defaultHeaders['Content-Type'] = 'text/html; charset=UTF-8';
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $headerString = '';
        foreach ($allHeaders as $name => $value) {
            $headerString .= "{$name}: {$value}\r\n";
        }

        // Ha nincsenek csatolmányok, egyszerű üzenet
        if (empty($attachments)) {
            return $headerString."\r\n".$body;
        }

        // Multipart üzenet csatolmányokkal
        $message = $headerString."\r\n";
        $message .= "This is a multi-part message in MIME format.\r\n\r\n";

        // HTML body part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body."\r\n\r\n";

        // Csatolmányok
        foreach ($attachments as $attachment) {
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['filename']}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$attachment['filename']}\"\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment['content']))."\r\n";
        }

        // Záró boundary
        $message .= "--{$boundary}--\r\n";

        return $message;
    }
}
