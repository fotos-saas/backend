<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversionJob extends Model
{
    protected $fillable = [
        'job_name',
        'status',
        'total_files',
        'processed_files',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'total_files' => 'integer',
            'processed_files' => 'integer',
        ];
    }

    /**
     * Get the conversion media records for this job
     */
    public function media(): HasMany
    {
        return $this->hasMany(ConversionMedia::class);
    }

    /**
     * Scope: Eager load media with Spatie media library relationship
     */
    public function scopeWithMediaFiles($query)
    {
        return $query->with(['media' => function ($query) {
            $query->with('media');
        }]);
    }

    /**
     * Scope: Eager load pending media only
     */
    public function scopeWithPendingMedia($query)
    {
        return $query->with(['media' => function ($query) {
            $query->where('conversion_status', 'pending')->with('media');
        }]);
    }

    /**
     * Scope: Eager load failed media only
     */
    public function scopeWithFailedMedia($query)
    {
        return $query->with(['media' => function ($query) {
            $query->where('conversion_status', 'failed')->with('media');
        }]);
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->total_files === 0) {
            return 0;
        }

        return (int) round(($this->processed_files / $this->total_files) * 100);
    }

    /**
     * Check if the job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job has failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the job is currently converting
     */
    public function isConverting(): bool
    {
        return $this->status === 'converting';
    }
}
