<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabloSamplePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_project_id',
        'title',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TabloSamplePackageVersion::class, 'package_id')
            ->orderByDesc('version_number');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Következő verziószám lekérése
     */
    public function getNextVersionNumber(): int
    {
        return ($this->versions()->max('version_number') ?? 0) + 1;
    }
}
