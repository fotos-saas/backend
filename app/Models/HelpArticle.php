<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * HelpArticle - Tudásbázis cikkek.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $content_plain
 * @property string $category
 * @property array $target_roles
 * @property array $target_plans
 * @property array $related_routes
 * @property array $keywords
 * @property string|null $feature_key
 * @property bool $is_published
 * @property bool $is_faq
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpArticle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'content_plain',
        'category',
        'target_roles',
        'target_plans',
        'related_routes',
        'keywords',
        'feature_key',
        'is_published',
        'is_faq',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_plans' => 'array',
            'related_routes' => 'array',
            'keywords' => 'array',
            'is_published' => 'boolean',
            'is_faq' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HelpArticle $article) {
            if (empty($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
            $article->content_plain = static::stripMarkdown($article->content);
        });

        static::updating(function (HelpArticle $article) {
            if ($article->isDirty('content')) {
                $article->content_plain = static::stripMarkdown($article->content);
            }
        });
    }

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public static function stripMarkdown(string $content): string
    {
        $text = preg_replace('/#{1,6}\s*/', '', $content);
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);
        $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
        $text = preg_replace('/`{1,3}(.*?)`{1,3}/s', '$1', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFaq($query)
    {
        return $query->where('is_faq', true);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('target_roles', $role);
    }

    public function scopeForPlan($query, string $plan)
    {
        return $query->whereJsonContains('target_plans', $plan);
    }

    public function scopeForRoute($query, string $route)
    {
        return $query->whereJsonContains('related_routes', $route);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
