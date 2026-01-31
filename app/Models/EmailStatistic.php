<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailStatistic extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'smtp_account_id',
        'email_template_id',
        'date',
        'hour',
        'sent_count',
        'failed_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'unsubscribed_count',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'bounced_count' => 'integer',
        'unsubscribed_count' => 'integer',
    ];

    /**
     * Relationship: SMTP account
     */
    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    /**
     * Relationship: Email template
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    /**
     * Increment sent count
     */
    public function incrementSent(): void
    {
        $this->increment('sent_count');
    }

    /**
     * Increment failed count
     */
    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    /**
     * Increment opened count
     */
    public function incrementOpened(): void
    {
        $this->increment('opened_count');
    }

    /**
     * Increment clicked count
     */
    public function incrementClicked(): void
    {
        $this->increment('clicked_count');
    }

    /**
     * Increment bounced count
     */
    public function incrementBounced(): void
    {
        $this->increment('bounced_count');
    }

    /**
     * Increment unsubscribed count
     */
    public function incrementUnsubscribed(): void
    {
        $this->increment('unsubscribed_count');
    }

    /**
     * Get or create statistic record for current hour
     */
    public static function getOrCreateForNow(?int $smtpAccountId, ?int $emailTemplateId): self
    {
        $now = now();

        return static::firstOrCreate(
            [
                'smtp_account_id' => $smtpAccountId,
                'email_template_id' => $emailTemplateId,
                'date' => $now->toDateString(),
                'hour' => $now->hour,
            ],
            [
                'sent_count' => 0,
                'failed_count' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
                'bounced_count' => 0,
                'unsubscribed_count' => 0,
            ]
        );
    }

    /**
     * Calculate open rate percentage
     */
    public function getOpenRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->opened_count / $this->sent_count) * 100, 2);
    }

    /**
     * Calculate click rate percentage
     */
    public function getClickRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->sent_count) * 100, 2);
    }

    /**
     * Calculate bounce rate percentage
     */
    public function getBounceRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->bounced_count / $this->sent_count) * 100, 2);
    }
}
