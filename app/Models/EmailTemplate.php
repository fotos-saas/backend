<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'body',
        'available_variables',
        'is_active',
        'smtp_account_id',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => AsArrayObject::class,
            'is_active' => 'boolean',
        ];
    }

    public function emailEvents(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function smtpAccount(): BelongsTo
    {
        return $this->belongsTo(SmtpAccount::class);
    }

    public function emailStatistics(): HasMany
    {
        return $this->hasMany(EmailStatistic::class);
    }
}
