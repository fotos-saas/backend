<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TabloPostMedia - Hozzászólás média csatolmányok.
 *
 * @property int $id
 * @property int $tablo_discussion_post_id
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $file_size
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPostMedia extends Model
{
    use HasFactory;

    protected $table = 'tablo_post_media';

    protected $fillable = [
        'tablo_discussion_post_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(TabloDiscussionPost::class, 'tablo_discussion_post_id');
    }

    /**
     * Get full URL
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Check if image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get human readable file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(): bool
    {
        return Storage::disk('public')->delete($this->file_path);
    }

    /**
     * Boot: delete file on model deletion
     */
    protected static function booted(): void
    {
        static::deleting(function (TabloPostMedia $media) {
            $media->deleteFile();
        });
    }
}
