<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabloContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'name',
        'email',
        'phone',
        'note',
        'is_primary',
        'call_count',
        'sms_count',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'call_count' => 'integer',
            'sms_count' => 'integer',
            'last_contacted_at' => 'datetime',
        ];
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get formatted contact info
     */
    public function getContactInfoAttribute(): string
    {
        $parts = [];

        if ($this->email) {
            $parts[] = $this->email;
        }

        if ($this->phone) {
            $parts[] = $this->phone;
        }

        return implode(' | ', $parts);
    }

    /**
     * Hívás regisztrálása
     */
    public function registerCall(): void
    {
        $this->increment('call_count');
        $this->update(['last_contacted_at' => now()]);
    }

    /**
     * SMS regisztrálása
     */
    public function registerSms(): void
    {
        $this->increment('sms_count');
        $this->update(['last_contacted_at' => now()]);
    }

    /**
     * Összes kapcsolatfelvétel száma
     */
    public function getTotalContactsAttribute(): int
    {
        return $this->call_count + $this->sms_count;
    }
}
