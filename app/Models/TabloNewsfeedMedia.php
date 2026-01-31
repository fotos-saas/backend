<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TabloNewsfeedMedia - Hírfolyam média fájlok.
 *
 * @property int $id
 * @property int $tablo_newsfeed_post_id
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $file_size
 * @property bool $is_image
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloNewsfeedMedia extends Model
{
    use HasFactory;

    protected $table = 'tablo_newsfeed_media';

    protected $fillable = [
        'tablo_newsfeed_post_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'is_image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_image' => 'boolean',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(TabloNewsfeedPost::class, 'tablo_newsfeed_post_id');
    }

    /**
     * Get public URL
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(): void
    {
        if (Storage::disk('public')->exists($this->file_path)) {
            Storage::disk('public')->delete($this->file_path);
        }
    }

    /**
     * Boot method for cleanup
     */
    protected static function booted(): void
    {
        static::deleting(function (TabloNewsfeedMedia $media) {
            $media->deleteFile();
        });
    }
}
