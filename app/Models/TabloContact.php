<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TabloContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'name',
        'email',
        'phone',
        'note',
        'call_count',
        'sms_count',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'call_count' => 'integer',
            'sms_count' => 'integer',
            'last_contacted_at' => 'datetime',
        ];
    }

    /**
     * Get the partner that owns this contact.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    /**
     * Get the projects this contact is linked to.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloProject::class,
            'tablo_project_contacts',
            'tablo_contact_id',
            'tablo_project_id'
        )->withPivot('is_primary')->withTimestamps();
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

    /**
     * Check if this contact is primary for a given project.
     */
    public function isPrimaryForProject(int $projectId): bool
    {
        $pivot = $this->projects()->where('tablo_projects.id', $projectId)->first()?->pivot;

        return $pivot?->is_primary ?? false;
    }

    /**
     * Get the first project this contact is linked to (for backward compatibility).
     */
    public function getFirstProjectAttribute(): ?TabloProject
    {
        return $this->projects()->first();
    }
}
