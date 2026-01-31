<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Album extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'name',
        'class_id',
        'created_by_user_id',
        'work_session_id',
        'date',
        'status',
        'visibility',
        'flags',
        'thumbnail',
        'package_id',
        'price_list_id',
        'zip_processing_status',
        'zip_total_images',
        'zip_processed_images',
        'face_grouping_status',
        'face_total_photos',
        'face_processed_photos',
        'parent_album_id',
    ];

    protected $casts = [
        'visibility' => 'string',
        'date' => 'date',
        'flags' => 'array',
    ];

    /**
     * DEPRECATED: Use schoolClasses() instead (many-to-many)
     * Backward compatibility: Single class relationship
     *
     * @deprecated Will be removed in v2.0. Use schoolClasses() instead.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * School classes (many-to-many relationship)
     * An album can belong to multiple school classes (e.g., joint graduation)
     */
    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'album_school_class',
            'album_id',
            'school_class_id'
        )->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Backward compatibility alias
    public function owner(): BelongsTo
    {
        return $this->createdBy();
    }

    public function workSession(): BelongsToMany
    {
        return $this->belongsToMany(WorkSession::class)->withTimestamps();
    }

    public function workSessions(): BelongsToMany
    {
        return $this->belongsToMany(WorkSession::class)->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Get the face groups for this album
     */
    public function faceGroups(): HasMany
    {
        return $this->hasMany(FaceGroup::class);
    }

    /**
     * Get all users who have assigned photos in this album
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'photos', 'album_id', 'assigned_user_id')
            ->distinct()
            ->withPivot('id')
            ->wherePivotNotNull('assigned_user_id');
    }

    /**
     * Get thumbnail URL (first photo or stored thumbnail)
     */
    public function getThumbnailAttribute(): ?string
    {
        // If thumbnail is stored, return it
        if ($this->attributes['thumbnail'] ?? null) {
            return $this->attributes['thumbnail'];
        }

        // Otherwise, get first photo's thumbnail
        $firstPhoto = $this->photos()->first();
        if ($firstPhoto) {
            return $firstPhoto->getThumbUrl();
        }

        return null;
    }

    /**
     * Get default flags structure
     */
    public static function getDefaultFlags(): array
    {
        return [
            'workflow' => 'default',
            'allowRetouch' => false,
            'allowGuestShare' => true,
            'enableCoupons' => true,
            'maxSelectable' => null,
            'accessMode' => null,
        ];
    }

    /**
     * Check if ZIP processing is in progress
     */
    public function isZipProcessing(): bool
    {
        return in_array($this->zip_processing_status, ['pending', 'processing']);
    }

    /**
     * Get ZIP processing progress percentage
     */
    public function getZipProgress(): ?int
    {
        if (! $this->zip_total_images || $this->zip_total_images === 0) {
            return null;
        }

        return (int) round(($this->zip_processed_images / $this->zip_total_images) * 100);
    }

    /**
     * Parent album relationship
     */
    public function parentAlbum(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'parent_album_id');
    }

    /**
     * Child albums relationship
     */
    public function childAlbums(): HasMany
    {
        return $this->hasMany(Album::class, 'parent_album_id');
    }
}
