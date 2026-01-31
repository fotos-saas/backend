<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * TabloGuestSession - Vendég session kezelés szavazásokhoz és fórumhoz.
 *
 * @property int $id
 * @property int|null $tablo_project_id
 * @property string $session_token UUID v4
 * @property string|null $device_identifier Fingerprint
 * @property string $guest_name Bekért név
 * @property string|null $guest_email Email ha megadta
 * @property string|null $ip_address Ban evasion prevention
 * @property bool $is_banned Bannolva van-e
 * @property bool $is_extra Extra tag (tanár, egyéb vendég) - nem számít bele a létszámba
 * @property bool $is_coordinator Kapcsolattartó (kóddal belépett) - mindenkit bökhet
 * @property int|null $tablo_missing_person_id Párosított személy ID
 * @property string $verification_status Verifikáció státusz (verified, pending, rejected)
 * @property \Carbon\Carbon|null $last_activity_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloGuestSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'session_token',
        'device_identifier',
        'guest_name',
        'guest_email',
        'ip_address',
        'is_banned',
        'is_extra',
        'is_coordinator',
        'tablo_missing_person_id',
        'verification_status',
        'restore_token',
        'restore_token_expires_at',
        'last_activity_at',
        // Gamification
        'points',
        'rank_level',
        'posts_count',
        'replies_count',
        'likes_received',
        'likes_given',
    ];

    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'is_extra' => 'boolean',
            'is_coordinator' => 'boolean',
            'tablo_missing_person_id' => 'integer',
            'last_activity_at' => 'datetime',
            'restore_token_expires_at' => 'datetime',
            // Gamification
            'points' => 'integer',
            'rank_level' => 'integer',
            'posts_count' => 'integer',
            'replies_count' => 'integer',
            'likes_received' => 'integer',
            'likes_given' => 'integer',
        ];
    }

    /**
     * Boot: automatikus session_token generálás
     */
    protected static function booted(): void
    {
        static::creating(function (TabloGuestSession $session) {
            if (empty($session->session_token)) {
                $session->session_token = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get the associated missing person (tablón szereplő személy)
     */
    public function missingPerson(): BelongsTo
    {
        return $this->belongsTo(TabloMissingPerson::class, 'tablo_missing_person_id');
    }

    /**
     * Get votes by this guest
     */
    public function votes(): HasMany
    {
        return $this->hasMany(TabloPollVote::class, 'tablo_guest_session_id');
    }

    /**
     * Get posts by this guest
     */
    public function posts(): HasMany
    {
        return $this->hasMany(TabloDiscussionPost::class, 'author_id')
            ->where('author_type', 'guest');
    }

    /**
     * Check if session is active (not banned)
     */
    public function isActive(): bool
    {
        return ! $this->is_banned;
    }

    /**
     * Ban this guest
     */
    public function ban(): void
    {
        $this->update(['is_banned' => true]);
    }

    /**
     * Unban this guest
     */
    public function unban(): void
    {
        $this->update(['is_banned' => false]);
    }

    /**
     * Update last activity
     */
    public function updateLastActivity(): bool
    {
        $this->last_activity_at = now();

        return $this->save();
    }

    /**
     * Scope for active (not banned) sessions
     */
    public function scopeActive($query)
    {
        return $query->where('is_banned', false);
    }

    /**
     * Scope for banned sessions
     */
    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    /**
     * Scope for regular members (not extra, not banned)
     */
    public function scopeRegularMembers($query)
    {
        return $query->where('is_extra', false)->where('is_banned', false);
    }

    /**
     * Scope for extra members (teachers, other guests)
     */
    public function scopeExtraMembers($query)
    {
        return $query->where('is_extra', true);
    }

    /**
     * Check if this is an extra member
     */
    public function isExtra(): bool
    {
        return $this->is_extra;
    }

    /**
     * Toggle extra status
     */
    public function toggleExtra(): void
    {
        $this->update(['is_extra' => ! $this->is_extra]);
    }

    /**
     * Scope for sessions with recent activity (last 30 days)
     */
    public function scopeRecentlyActive($query)
    {
        return $query->where('last_activity_at', '>=', now()->subDays(30));
    }

    /**
     * Find by session token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('session_token', $token)->first();
    }

    /**
     * Find by session token for a specific project
     */
    public static function findByTokenAndProject(string $token, int $projectId): ?self
    {
        return static::where('session_token', $token)
            ->where('tablo_project_id', $projectId)
            ->first();
    }

    // ==========================================
    // GAMIFICATION
    // ==========================================

    /**
     * Badge-ek kapcsolat
     */
    public function badges(): HasMany
    {
        return $this->hasMany(TabloUserBadge::class, 'tablo_guest_session_id');
    }

    /**
     * Pontszám naplók
     */
    public function pointLogs(): HasMany
    {
        return $this->hasMany(TabloPointLog::class, 'tablo_guest_session_id');
    }

    /**
     * Rang név lekérése
     */
    public function getRankNameAttribute(): string
    {
        return match ($this->rank_level) {
            1 => 'Újonc',
            2 => 'Tag',
            3 => 'Aktív tag',
            4 => 'Veterán',
            5 => 'Mester',
            6 => 'Legenda',
            default => 'Ismeretlen',
        };
    }

    /**
     * Következő rang szükséges pontja
     */
    public function getNextRankPointsAttribute(): ?int
    {
        $ranks = [
            1 => 0,
            2 => 25,
            3 => 100,
            4 => 250,
            5 => 500,
            6 => 1000,
        ];

        $nextLevel = $this->rank_level + 1;

        return $ranks[$nextLevel] ?? null;
    }

    /**
     * Haladás a következő rangig (0-100%)
     */
    public function getProgressToNextRankAttribute(): ?float
    {
        if (! $this->next_rank_points) {
            return null; // Már a legmagasabb rang
        }

        $currentRankPoints = [
            1 => 0, 2 => 25, 3 => 100, 4 => 250, 5 => 500, 6 => 1000,
        ][$this->rank_level];

        $pointsInCurrentRank = $this->points - $currentRankPoints;
        $pointsNeeded = $this->next_rank_points - $currentRankPoints;

        return min(100, ($pointsInCurrentRank / $pointsNeeded) * 100);
    }

    // ==========================================
    // POKE SYSTEM
    // ==========================================

    /**
     * Küldött bökések
     */
    public function sentPokes(): HasMany
    {
        return $this->hasMany(TabloPoke::class, 'from_guest_session_id');
    }

    /**
     * Kapott bökések
     */
    public function receivedPokes(): HasMany
    {
        return $this->hasMany(TabloPoke::class, 'target_guest_session_id');
    }

    /**
     * Napi bökés limitek
     */
    public function pokeDailyLimits(): HasMany
    {
        return $this->hasMany(TabloPokeDailyLimit::class, 'from_guest_session_id');
    }

    /**
     * Olvasatlan bökések száma
     */
    public function getUnreadPokesCountAttribute(): int
    {
        return $this->receivedPokes()->unread()->count();
    }

    // ==========================================
    // IDENTIFICATION (ONBOARDING)
    // ==========================================

    /**
     * Verifikáció státusz konstansok
     */
    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_REJECTED = 'rejected';

    /**
     * Van-e párosított személy (tablón szereplő)
     */
    public function hasPersonIdentification(): bool
    {
        return $this->tablo_missing_person_id !== null;
    }

    /**
     * Verifikálva van-e (nem pending, nem rejected)
     */
    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    /**
     * Pending státuszú-e (ütközés miatt várakozik)
     */
    public function isPending(): bool
    {
        return $this->verification_status === self::VERIFICATION_PENDING;
    }

    /**
     * Elutasított státuszú-e
     */
    public function isRejected(): bool
    {
        return $this->verification_status === self::VERIFICATION_REJECTED;
    }

    /**
     * Scope for verified sessions only
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    /**
     * Scope for pending sessions only
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', self::VERIFICATION_PENDING);
    }

    /**
     * Scope for rejected sessions only
     */
    public function scopeRejected($query)
    {
        return $query->where('verification_status', self::VERIFICATION_REJECTED);
    }

    /**
     * Scope for sessions with person identification
     */
    public function scopeWithPersonIdentification($query)
    {
        return $query->whereNotNull('tablo_missing_person_id');
    }
}
