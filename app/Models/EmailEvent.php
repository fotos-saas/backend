<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
    protected $fillable = [
        'event_type',
        'email_template_id',
        'recipient_type',
        'custom_recipients',
        'conditions',
        'attachments',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'custom_recipients' => AsArrayObject::class,
            'conditions' => AsArrayObject::class,
            'attachments' => AsArrayObject::class,
            'is_active' => 'boolean',
        ];
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }
}
