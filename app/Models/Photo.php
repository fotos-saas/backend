<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Photo extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'album_id',
        'path',
        'original_filename',
        'hash',
        'width',
        'height',
        'assigned_user_id',
        'claimed_by_user_id',
        'user_comment',
        'gender',
        'face_direction',
        'face_detected',
        'age',
        'face_subject',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        // Main photo collection
        $this->addMediaCollection('photo')
            ->singleFile()
            ->useDisk('public')
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(300)
                    ->height(300)
                    ->sharpen(10)
                    ->nonQueued();

                $this->addMediaConversion('preview')
                    ->width(1200)
                    ->height(1200)
                    ->sharpen(10)
                    ->nonQueued();
            });
    }

    /**
     * Register media conversions (applied to all collections)
     *
     * NOTE: thumb and preview conversions are already defined in registerMediaCollections()
     * for the 'photo' collection. This method is kept for potential future use with
     * dynamic collections (e.g., photo_version_*) if needed.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Conversions are defined in registerMediaCollections() for the 'photo' collection
        // to avoid duplicate conversions and multiple watermark applications
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PhotoNote::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the face groups this photo belongs to
     */
    public function faceGroups(): BelongsToMany
    {
        return $this->belongsToMany(FaceGroup::class, 'face_group_photo')
            ->withPivot('confidence')
            ->withTimestamps();
    }

    /**
     * Get photo versions (last 20 versions)
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PhotoVersion::class)->latest()->limit(20);
    }

    /**
     * Get thumbnail URL (300x300)
     */
    public function getThumbUrl(): ?string
    {
        $media = $this->getFirstMedia('photo');

        return $media?->getUrl('thumb');
    }

    /**
     * Get preview URL (1200x1200)
     */
    public function getPreviewUrl(): ?string
    {
        $media = $this->getFirstMedia('photo');

        return $media?->getUrl('preview');
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['path', 'assigned_user_id', 'gender', 'face_direction', 'user_comment'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Scope: Only photos that are available for claiming (not claimed yet)
     */
    public function scopeAvailableForClaiming($query)
    {
        return $query->whereNull('claimed_by_user_id');
    }

    /**
     * Scope: Photos claimed by a specific user
     */
    public function scopeClaimedBy($query, User $user)
    {
        return $query->where('claimed_by_user_id', $user->id);
    }
}
