<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloMissingPerson extends Model
{
    use HasFactory;

    protected $table = 'tablo_missing_persons';

    protected $fillable = [
        'tablo_project_id',
        'name',
        'email',
        'type', // student, teacher
        'local_id',
        'note',
        'position',
        'media_id',
    ];

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get the assigned photo (Media record)
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Thumbnail URL (40×40px) - URL encoded for special characters
     */
    protected function photoThumbUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->photo) {
                    return null;
                }

                $url = $this->photo->getUrl('thumb');
                // URL encode the path for special characters (Hungarian accents)
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
                // URL encode the path for special characters (Hungarian accents)
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
     * Has assigned photo?
     */
    public function hasPhoto(): bool
    {
        return $this->media_id !== null;
    }

    /**
     * Iskola + Osztály kombinált megjelenítés csoportosításhoz
     * Pl: "Petőfi Gimnázium 12.A"
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
     * Csak verified státuszú session-t adja vissza
     */
    public function guestSession(): HasOne
    {
        return $this->hasOne(TabloGuestSession::class, 'tablo_missing_person_id')
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED);
    }

    /**
     * Get all guest sessions claiming this person (including pending)
     */
    public function allGuestSessions()
    {
        return $this->hasMany(TabloGuestSession::class, 'tablo_missing_person_id');
    }

    /**
     * Van-e már valaki párosítva ehhez a személyhez (verified)
     */
    public function isClaimed(): bool
    {
        return $this->guestSession()->exists();
    }

    /**
     * Típus címke magyarul
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'student' => 'Diák',
            'teacher' => 'Tanár',
            default => $this->type,
        };
    }
}
