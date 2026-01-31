<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $fillable = [
        'name',
        'album_id',
        'is_default',
        'default_print_size_id',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Album relationship (optional)
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Prices relationship
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Default print size relationship
     */
    public function defaultPrintSize(): BelongsTo
    {
        return $this->belongsTo(PrintSize::class, 'default_print_size_id');
    }

    /**
     * Get default print size (with fallback to cheapest)
     */
    public function getDefaultPrintSize(): ?PrintSize
    {
        // If default is set, return it
        if ($this->default_print_size_id && $this->defaultPrintSize) {
            return $this->defaultPrintSize;
        }

        // Fallback: cheapest print size
        $cheapestPrice = $this->prices()
            ->with('printSize')
            ->orderBy('price', 'asc')
            ->first();

        return $cheapestPrice?->printSize;
    }

    /**
     * Scope query to default price list
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Mark this price list as default and unmark others
     */
    public function markAsDefault(): void
    {
        // Start transaction
        \DB::transaction(function () {
            // Unmark all others
            self::where('id', '!=', $this->id)->update(['is_default' => false]);

            // Mark this one as default
            $this->update(['is_default' => true]);
        });
    }
}
