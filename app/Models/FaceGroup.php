<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FaceGroup extends Model
{
    protected $fillable = [
        'album_id',
        'name',
        'representative_photo_id',
    ];

    /**
     * Get the album that owns the face group
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Get the photos in this face group
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'face_group_photo')
            ->withPivot('confidence')
            ->withTimestamps();
    }

    /**
     * Get the representative photo for this group
     */
    public function representativePhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'representative_photo_id');
    }
}
