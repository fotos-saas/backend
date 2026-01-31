<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'school',
        'grade',
        'label',
    ];

    /**
     * Get the full label with school name and class label
     */
    public function getFullLabelAttribute(): string
    {
        return "{$this->school} - {$this->label}";
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'class_id');
    }

    /**
     * Albums (many-to-many relationship)
     * A school class can have multiple albums (e.g., multiple photography sessions)
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(
            Album::class,
            'album_school_class',
            'school_class_id',
            'album_id'
        )->withTimestamps();
    }
}
