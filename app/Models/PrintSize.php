<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintSize extends Model
{
    protected $fillable = [
        'name',
        'width_mm',
        'height_mm',
        'weight_grams',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * Prices relationship
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Package items relationship
     */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }
}
