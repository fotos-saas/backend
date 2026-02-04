<?php

namespace App\Models;

use App\Enums\TabloProjectStatus;
use App\Traits\HasAccessCode;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloProject extends Model implements HasMedia
{
    use HasAccessCode, HasFactory, InteractsWithMedia;

    protected static function booted(): void
    {
        // Új projekt létrehozásakor automatikusan generáljuk a share_token-t
        static::creating(function (TabloProject $project) {
            if (empty($project->share_token)) {
                $project->share_token = $project->generateShareToken();
                $project->share_token_enabled = true;
            }
        });

        static::saving(function (TabloProject $project) {
            // Ha nincs name megadva, generáljuk automatikusan
            if (empty($project->name)) {
                $parts = [];

                if ($project->school_id) {
                    $school = TabloSchool::find($project->school_id);
                    if ($school) {
                        $parts[] = $school->name;
                    }
                }

                if ($project->class_name) {
                    $parts[] = $project->class_name;
                }

                if ($project->class_year) {
                    $parts[] = $project->class_year;
                }

                $project->name = implode(' ', $parts) ?: 'Névtelen projekt';
            }
        });
    }

    protected $fillable = [
        'partner_id',
        'tablo_gallery_id',
        'school_id',
        'tablo_status_id',
        'fotocms_id',
        'external_id',
        'name',
        'class_name',
        'class_year',
        'status',
        'access_code',  // Fillable for Filament admin forms
        'access_code_enabled',
        'access_code_expires_at',
        'share_token_enabled',
        'share_token_expires_at',
        'user_status',
        'user_status_color',
        'admin_preview_token_expires_at',
        'is_aware',
        'has_new_missing_photos',
        'max_template_selections',
        'data',
        'photo_date',
        'deadline',
        'sync_at',
        'expected_class_size',
        'actual_guests_count',
        'custom_properties',
    ];

    /**
     * SECURITY: Sensitive tokens must NEVER be mass-assigned from external input.
     * share_token and admin_preview_token are only set explicitly in code.
     * access_code is fillable for Filament admin but hidden from API.
     */
    protected $guarded = [
        'share_token',
        'admin_preview_token',
    ];

    /**
     * SECURITY: Sensitive tokens hidden from API responses.
     * Note: access_code NOT hidden here because Filament admin needs it.
     * API endpoints should manually exclude sensitive fields.
     */
    protected $hidden = [
        'share_token',
        'admin_preview_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TabloProjectStatus::class,
            'access_code_enabled' => 'boolean',
            'access_code_expires_at' => 'datetime',
            'share_token_enabled' => 'boolean',
            'share_token_expires_at' => 'datetime',
            'admin_preview_token_expires_at' => 'datetime',
            'is_aware' => 'boolean',
            'has_new_missing_photos' => 'boolean',
            'max_template_selections' => 'integer',
            'data' => 'array',
            'photo_date' => 'date',
            'deadline' => 'date',
            'sync_at' => 'datetime',
            'expected_class_size' => 'integer',
            'actual_guests_count' => 'integer',
            'custom_properties' => 'array',
        ];
    }

    /**
     * Dinamikus megnevezés: Iskola - Osztály Év
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $schoolName = $this->school?->name ?? 'Ismeretlen iskola';
                $className = $this->class_name ?? '';
                $classYear = $this->class_year ?? '';

                $name = $schoolName;
                if ($className) {
                    $name .= ' - '.$className;
                }
                if ($classYear) {
                    $name .= ' '.$classYear;
                }

                return $name;
            }
        );
    }

    /**
     * Get the partner
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    /**
     * Get the gallery associated with this project.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(TabloGallery::class, 'tablo_gallery_id');
    }

    /**
     * Get the school
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(TabloSchool::class, 'school_id');
    }

    /**
     * Get the tablo status (user-facing project status)
     */
    public function tabloStatus(): BelongsTo
    {
        return $this->belongsTo(TabloStatus::class);
    }

    /**
     * Get contacts for this project (many-to-many via pivot).
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloContact::class,
            'tablo_project_contacts',
            'tablo_project_id',
            'tablo_contact_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    /**
     * Get the primary contact for this project.
     */
    public function primaryContact(): ?TabloContact
    {
        return $this->contacts()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get notes for this project
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TabloNote::class, 'tablo_project_id');
    }

    /**
     * Get persons for this project (diákok és tanárok)
     */
    public function persons(): HasMany
    {
        return $this->hasMany(TabloPerson::class, 'tablo_project_id');
    }

    /**
     * @deprecated Use persons() instead - kept for backward compatibility
     */
    public function missingPersons(): HasMany
    {
        return $this->persons();
    }

    /**
     * Get emails for this project
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ProjectEmail::class, 'tablo_project_id');
    }

    /**
     * Get order analyses for this project
     */
    public function orderAnalyses(): HasMany
    {
        return $this->hasMany(TabloOrderAnalysis::class, 'tablo_project_id');
    }

    /**
     * Get guest sessions for this project
     */
    public function guestSessions(): HasMany
    {
        return $this->hasMany(TabloGuestSession::class, 'tablo_project_id');
    }

    /**
     * Get QR registration codes for this project
     */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrRegistrationCode::class, 'tablo_project_id');
    }

    /**
     * Get active (not banned) guest sessions
     */
    public function activeGuestSessions(): HasMany
    {
        return $this->guestSessions()->where('is_banned', false);
    }

    /**
     * Get polls for this project
     */
    public function polls(): HasMany
    {
        return $this->hasMany(TabloPoll::class, 'tablo_project_id');
    }

    /**
     * Get active polls
     */
    public function activePolls(): HasMany
    {
        return $this->polls()->where('is_active', true);
    }

    /**
     * Get discussions for this project
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(TabloDiscussion::class, 'tablo_project_id');
    }

    /**
     * Get selected templates for this project.
     */
    public function selectedTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloSampleTemplate::class,
            'tablo_project_template_selections',
            'tablo_project_id',
            'template_id'
        )->withPivot('priority')->withTimestamps()->orderByPivot('priority');
    }

    /**
     * Check if a template is selected.
     */
    public function hasSelectedTemplate(int $templateId): bool
    {
        return $this->selectedTemplates()->where('tablo_sample_templates.id', $templateId)->exists();
    }

    /**
     * Can select more templates?
     */
    public function canSelectMoreTemplates(): bool
    {
        $max = $this->max_template_selections ?? 3;
        return $this->selectedTemplates()->count() < $max;
    }

    /**
     * Get next available priority.
     */
    public function getNextTemplatePriority(): int
    {
        $maxPriority = $this->selectedTemplates()->max('tablo_project_template_selections.priority');
        return ($maxPriority ?? 0) + 1;
    }

    /**
     * Van-e leadott megrendelése a projektnek?
     */
    public function hasOrderAnalysis(): bool
    {
        return $this->orderAnalyses()->where('status', 'completed')->exists();
    }

    /**
     * Van-e kitöltött megrendelési adat a projektben?
     * A véglegesítés során kerülnek mentésre a data mezőbe.
     */
    public function hasOrderData(): bool
    {
        $data = $this->data;

        // Nincs data mező, vagy üres
        if (empty($data) || ! is_array($data)) {
            return false;
        }

        // Ellenőrizzük, hogy van-e legalább egy releváns mező kitöltve
        // A véglegesítés során ezek a mezők kerülnek mentésre
        $relevantFields = ['quote', 'font_family', 'color', 'description', 'sort_type'];

        foreach ($relevantFields as $field) {
            if (! empty($data[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legutóbbi sikeres megrendelés elemzés
     */
    public function getLatestOrderAnalysisAttribute(): ?TabloOrderAnalysis
    {
        return $this->orderAnalyses()->where('status', 'completed')->latest()->first();
    }

    /**
     * Megválaszolatlan bejövő emailek száma
     */
    public function getUnansweredEmailsCountAttribute(): int
    {
        return $this->emails()->needsReply()->count();
    }

    /**
     * Utolsó email dátuma
     */
    public function getLastEmailDateAttribute(): ?\Carbon\Carbon
    {
        $maxDate = $this->emails()->max('email_date');

        return $maxDate ? \Carbon\Carbon::parse($maxDate) : null;
    }

    /**
     * Update actual guests count cache
     */
    public function updateGuestsCount(): void
    {
        $this->update(['actual_guests_count' => $this->activeGuestSessions()->count()]);
    }

    /**
     * Get participation rate for a poll
     */
    public function getPollParticipationRate(?TabloPoll $poll = null): float
    {
        if (! $this->expected_class_size || $this->expected_class_size === 0) {
            return 0;
        }

        $voters = $poll
            ? $poll->unique_voters_count
            : $this->activeGuestSessions()->count();

        return round(($voters / $this->expected_class_size) * 100, 1);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, TabloProjectStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by partner
     */
    public function scopeByPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    /**
     * Scope aware projects
     */
    public function scopeAware($query)
    {
        return $query->where('is_aware', true);
    }

    /**
     * Scope not aware projects
     */
    public function scopeNotAware($query)
    {
        return $query->where('is_aware', false);
    }

    /**
     * Register media collections for samples
     */
    public function registerMediaCollections(): void
    {
        // Minta képek (samples)
        $this->addMediaCollection('samples')
            ->useDisk('public');

        // Partner által feltöltött, de még nem párosított képek
        $this->addMediaCollection('tablo_pending')
            ->useDisk('public');

        // Tablóra kerülő, párosított képek (aktív)
        $this->addMediaCollection('tablo_photos')
            ->useDisk('public');

        // Lecserélt, archivált képek (régi verziók)
        $this->addMediaCollection('tablo_archived')
            ->useDisk('public');

        // Talonba mentett képek (párosítás nélkül)
        $this->addMediaCollection('talon_photos')
            ->useDisk('public');
    }

    /**
     * Register media conversions (thumbnails)
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * Get access logs for this project
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(TabloProjectAccessLog::class, 'tablo_project_id');
    }

    /**
     * Generate unique share token (64 chars, URL-safe)
     */
    public function generateShareToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (static::where('share_token', $token)->exists());

        return $token;
    }

    /**
     * Check if share token is valid (enabled and not expired)
     */
    public function hasValidShareToken(): bool
    {
        if (! $this->share_token_enabled || ! $this->share_token) {
            return false;
        }

        if (! $this->share_token_expires_at) {
            return true; // Végtelen lejárat
        }

        return $this->share_token_expires_at->isFuture();
    }

    /**
     * Get the public share URL
     */
    public function getShareUrl(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return config('app.frontend_tablo_url') . '/share/' . $this->share_token;
    }

    /**
     * Generate one-time admin preview token (expires in 5 minutes)
     *
     * Note: Uses direct property assignment instead of update() because
     * admin_preview_token is guarded for security (prevents mass assignment).
     */
    public function generateAdminPreviewToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->admin_preview_token = $token;
        $this->admin_preview_token_expires_at = now()->addMinutes(5);
        $this->save();

        return $token;
    }

    /**
     * Consume admin preview token (invalidate after use)
     *
     * Note: Uses direct property assignment instead of update() because
     * admin_preview_token is guarded for security (prevents mass assignment).
     */
    public function consumeAdminPreviewToken(): void
    {
        $this->admin_preview_token = null;
        $this->admin_preview_token_expires_at = null;
        $this->save();
    }

    /**
     * Check if admin preview token is valid
     */
    public function hasValidAdminPreviewToken(?string $token): bool
    {
        if (! $this->admin_preview_token || ! $token) {
            return false;
        }

        if ($this->admin_preview_token !== $token) {
            return false;
        }

        if ($this->admin_preview_token_expires_at && $this->admin_preview_token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get admin preview URL
     */
    public function getAdminPreviewUrl(): string
    {
        $token = $this->generateAdminPreviewToken();

        return config('app.frontend_tablo_url') . '/preview/' . $token;
    }

    /**
     * Find project by share token
     */
    public static function findByShareToken(string $token): ?self
    {
        return static::where('share_token', $token)
            ->where('share_token_enabled', true)
            ->where(function ($q) {
                $q->whereNull('share_token_expires_at')
                    ->orWhere('share_token_expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Find project by admin preview token
     */
    public static function findByAdminPreviewToken(string $token): ?self
    {
        return static::where('admin_preview_token', $token)
            ->where(function ($q) {
                $q->whereNull('admin_preview_token_expires_at')
                    ->orWhere('admin_preview_token_expires_at', '>', now());
            })
            ->first();
    }
}
