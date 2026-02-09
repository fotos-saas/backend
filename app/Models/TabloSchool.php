<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabloSchool extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_id',
        'name',
        'city',
    ];

    /**
     * Get projects for this school
     */
    public function projects(): HasMany
    {
        return $this->hasMany(TabloProject::class, 'school_id');
    }

    /**
     * Get teachers for this school
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(TeacherArchive::class, 'school_id');
    }

    /**
     * Get partners that have this school linked (many-to-many via partner_schools pivot)
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(TabloPartner::class, 'partner_schools', 'school_id', 'partner_id')
            ->withTimestamps();
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(SchoolChangeLog::class, 'school_id');
    }
}
