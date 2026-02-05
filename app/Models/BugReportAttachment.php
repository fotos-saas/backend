<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BugReportAttachment extends Model
{
    protected $fillable = [
        'bug_report_id',
        'filename',
        'original_filename',
        'mime_type',
        'size_bytes',
        'storage_path',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function bugReport(): BelongsTo
    {
        return $this->belongsTo(BugReport::class);
    }

    public function getUrl(): string
    {
        return Storage::disk('public')->url($this->storage_path);
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024) . ' KB';
    }
}
