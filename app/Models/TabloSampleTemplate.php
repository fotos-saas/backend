<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * TabloSampleTemplate - Template for tablo designs.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $image_path
 * @property string|null $thumbnail_path
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_featured
 * @property array|null $tags
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloSampleTemplate extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'thumbnail_path',
        'sort_order',
        'is_active',
        'is_featured',
        'tags',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TabloSampleTemplate $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });

        static::updating(function (TabloSampleTemplate $template) {
            if ($template->isDirty('name') && ! $template->isDirty('slug')) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * Get categories for this template.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloSampleTemplateCategory::class,
            'tablo_sample_template_category',
            'template_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Get projects that selected this template.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            TabloProject::class,
            'tablo_project_template_selections',
            'template_id',
            'tablo_project_id'
        )->withPivot('priority')->withTimestamps();
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('template_image')
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * Register media conversions (thumbnails).
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->sharpen(5)
            ->nonQueued();
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured templates.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for ordered templates.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope for templates in a category.
     */
    public function scopeInCategory($query, int|string $categoryIdOrSlug)
    {
        return $query->whereHas('categories', function ($q) use ($categoryIdOrSlug) {
            if (is_numeric($categoryIdOrSlug)) {
                $q->where('tablo_sample_template_categories.id', $categoryIdOrSlug);
            } else {
                $q->where('tablo_sample_template_categories.slug', $categoryIdOrSlug);
            }
        });
    }

    /**
     * Get full image URL.
     * Returns relative URL for cross-origin compatibility.
     */
    public function getImageUrlAttribute(): string
    {
        // Try Spatie Media Library first
        $media = $this->getFirstMedia('template_image');
        if ($media) {
            return $media->getUrl();
        }

        // Fallback to image_path - return relative URL for frontend proxy
        if ($this->image_path) {
            return '/storage/' . $this->image_path;
        }

        return '/images/placeholder-template.jpg';
    }

    /**
     * Get thumbnail URL.
     * Returns relative URL for cross-origin compatibility.
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Try Spatie Media Library first
        $media = $this->getFirstMedia('template_image');
        if ($media) {
            return $media->getUrl('thumb');
        }

        // Fallback to thumbnail_path or image_path - return relative URL
        if ($this->thumbnail_path) {
            return '/storage/' . $this->thumbnail_path;
        }

        return $this->image_url;
    }

    /**
     * Get preview URL (medium size).
     */
    public function getPreviewUrlAttribute(): string
    {
        $media = $this->getFirstMedia('template_image');
        if ($media) {
            return $media->getUrl('preview');
        }

        return $this->image_url;
    }

    /**
     * Convert to API response format.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'imageUrl' => $this->image_url,
            'thumbnailUrl' => $this->thumbnail_url,
            'previewUrl' => $this->preview_url,
            'isFeatured' => $this->is_featured,
            'tags' => $this->tags ?? [],
            'categories' => $this->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ])->toArray(),
        ];
    }
}
