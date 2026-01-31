<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'email_template_id',
        'smtp_account_id',
        'priority',
        'event_type',
        'recipient_email',
        'recipient_user_id',
        'subject',
        'body',
        'attachments',
        'status',
        'error_message',
        'sent_at',
        'tracking_token',
        'opened_at',
        'open_count',
        'clicked_at',
        'click_count',
        'bounce_type',
        'bounced_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => AsArrayObject::class,
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }
}
