<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloPerson extends Model
{
    use HasFactory;

    protected $table = 'tablo_persons';

    protected $fillable = [
        'tablo_project_id',
        'name',
        'email',
        'type', // student, teacher
        'local_id',
        'note',
        'position',
        'media_id',
        'archive_id',
        'override_photo_id',
    ];

    // ============ Relationships ============

    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Legacy: direkt FK a médiához (deprecated, archive-ból jön a fotó)
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Override fotó (projekt-specifikus felülírás)
     */
    public function overridePhoto(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'override_photo_id');
    }

    /**
     * Tanár archive rekord (ha type=teacher)
     */
    public function teacherArchive(): BelongsTo
    {
        return $this->belongsTo(TeacherArchive::class, 'archive_id');
    }

    /**
     * Diák archive rekord (ha type=student)
     */
    public function studentArchive(): BelongsTo
    {
        return $this->belongsTo(StudentArchive::class, 'archive_id');
    }

    /**
     * Unified archive accessor (type-ból derivált)
     */
    public function getArchiveAttribute(): TeacherArchive|StudentArchive|null
    {
        if (!$this->archive_id) {
            return null;
        }

        return $this->type === 'teacher'
            ? $this->teacherArchive
            : $this->studentArchive;
    }

    // ============ Effective Photo Resolution ============

    /**
     * Effektív fotó URL: override → archive.active_photo → legacy media_id
     */
    public function getEffectivePhotoUrl(): ?string
    {
        // 1. Override fotó (projekt-specifikus)
        if ($this->override_photo_id) {
            return $this->overridePhoto?->getUrl();
        }

        // 2. Archive active_photo
        if ($this->archive_id) {
            $archive = $this->type === 'teacher'
                ? $this->teacherArchive
                : $this->studentArchive;

            if ($archive?->active_photo_id) {
                return $archive->activePhoto?->getUrl();
            }
        }

        // 3. Legacy media_id fallback
        if ($this->media_id) {
            return $this->photo?->getUrl();
        }

        return null;
    }

    /**
     * Effektív thumbnail URL: override → archive.active_photo → legacy media_id
     */
    public function getEffectivePhotoThumbUrl(): ?string
    {
        // 1. Override fotó
        if ($this->override_photo_id) {
            $media = $this->overridePhoto;
            if ($media) {
                try {
                    return $media->getUrl('thumb');
                } catch (\Throwable) {
                    return $media->getUrl();
                }
            }
            return null;
        }

        // 2. Archive active_photo
        if ($this->archive_id) {
            $archive = $this->type === 'teacher'
                ? $this->teacherArchive
                : $this->studentArchive;

            if ($archive?->active_photo_id && $archive->activePhoto) {
                try {
                    return $archive->activePhoto->getUrl('thumb');
                } catch (\Throwable) {
                    return $archive->activePhoto->getUrl();
                }
            }
        }

        // 3. Legacy media_id fallback
        return $this->photo_thumb_url;
    }

    /**
     * Van-e effektív fotó (bármely forrásból)?
     */
    public function hasEffectivePhoto(): bool
    {
        if ($this->override_photo_id) {
            return true;
        }

        if ($this->archive_id) {
            $archive = $this->type === 'teacher'
                ? $this->teacherArchive
                : $this->studentArchive;

            if ($archive?->active_photo_id) {
                return true;
            }
        }

        return $this->media_id !== null;
    }

    // ============ Scopes ============

    /**
     * Személyek effektív fotó nélkül (archive + override + legacy)
     */
    public function scopeWithoutEffectivePhoto($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('override_photo_id')
                ->where(function ($inner) {
                    // Nincs archive linkje
                    $inner->where(function ($noArchive) {
                        $noArchive->whereNull('archive_id')
                            ->whereNull('media_id');
                    })
                    // Vagy van archive, de nincs aktív fotója
                    ->orWhere(function ($withArchive) {
                        $withArchive->whereNotNull('archive_id')
                            ->where(function ($archiveCheck) {
                                // teacher archive aktív fotó nélkül
                                $archiveCheck->where(function ($teacher) {
                                    $teacher->where('type', 'teacher')
                                        ->whereDoesntHave('teacherArchive', fn ($q) => $q->whereNotNull('active_photo_id'));
                                })
                                // VAGY student archive aktív fotó nélkül
                                ->orWhere(function ($student) {
                                    $student->where('type', 'student')
                                        ->whereDoesntHave('studentArchive', fn ($q) => $q->whereNotNull('active_photo_id'));
                                });
                            })
                            ->whereNull('media_id');
                    });
                });
        });
    }

    /**
     * Személyek effektív fotóval
     */
    public function scopeWithEffectivePhoto($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('override_photo_id')
                ->orWhereNotNull('media_id')
                ->orWhere(function ($archiveQ) {
                    $archiveQ->whereNotNull('archive_id')
                        ->where(function ($typeQ) {
                            $typeQ->where(function ($teacher) {
                                $teacher->where('type', 'teacher')
                                    ->whereHas('teacherArchive', fn ($q) => $q->whereNotNull('active_photo_id'));
                            })
                            ->orWhere(function ($student) {
                                $student->where('type', 'student')
                                    ->whereHas('studentArchive', fn ($q) => $q->whereNotNull('active_photo_id'));
                            });
                        });
                });
        });
    }

    // ============ Legacy Accessors ============

    /**
     * Thumbnail URL (40x40px) - URL encoded for special characters
     */
    protected function photoThumbUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->photo) {
                    return null;
                }

                $url = $this->photo->getUrl('thumb');
                $parts = parse_url($url);
                if (isset($parts['path'])) {
                    $pathSegments = explode('/', $parts['path']);
                    $encodedSegments = array_map(fn($s) => rawurlencode($s), $pathSegments);
                    $parts['path'] = implode('/', $encodedSegments);
                }

                return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                    . ($parts['host'] ?? '')
                    . (isset($parts['port']) ? ':' . $parts['port'] : '')
                    . ($parts['path'] ?? '');
            }
        );
    }

    /**
     * Full photo URL - URL encoded for special characters
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->photo) {
                    return null;
                }

                $url = $this->photo->getUrl();
                $parts = parse_url($url);
                if (isset($parts['path'])) {
                    $pathSegments = explode('/', $parts['path']);
                    $encodedSegments = array_map(fn($s) => rawurlencode($s), $pathSegments);
                    $parts['path'] = implode('/', $encodedSegments);
                }

                return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
                    . ($parts['host'] ?? '')
                    . (isset($parts['port']) ? ':' . $parts['port'] : '')
                    . ($parts['path'] ?? '');
            }
        );
    }

    /**
     * Has assigned photo? (legacy - use hasEffectivePhoto() instead)
     */
    public function hasPhoto(): bool
    {
        return $this->media_id !== null;
    }

    // ============ Helpers ============

    /**
     * Iskola + Osztaly kombinalt megjelenítes csoportosítashoz
     */
    protected function schoolAndClass(): Attribute
    {
        return Attribute::make(
            get: fn () => trim(
                ($this->project?->school?->name ?? '') . ' ' . ($this->project?->class_name ?? '')
            )
        );
    }

    /**
     * Get the guest session associated with this person (if any)
     */
    public function guestSession(): HasOne
    {
        return $this->hasOne(TabloGuestSession::class, 'tablo_person_id')
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED);
    }

    /**
     * Get all guest sessions claiming this person (including pending)
     */
    public function allGuestSessions()
    {
        return $this->hasMany(TabloGuestSession::class, 'tablo_person_id');
    }

    /**
     * Van-e mar valaki parosítva ehhez a szemelyhez (verified)
     */
    public function isClaimed(): bool
    {
        return $this->guestSession()->exists();
    }

    /**
     * Szamlazasi terhelesek
     */
    public function billingCharges(): HasMany
    {
        return $this->hasMany(GuestBillingCharge::class, 'tablo_person_id');
    }

    /**
     * Típus címke magyarul
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'student' => 'Diak',
            'teacher' => 'Tanar',
            default => $this->type,
        };
    }
}
