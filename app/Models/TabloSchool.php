<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
