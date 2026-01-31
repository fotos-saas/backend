<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tablo Project Access Log - Audit trail for project access events.
 *
 * Tracks all access events including:
 * - 6-digit code login
 * - Share token access
 * - Admin preview access
 */
class TabloProjectAccessLog extends Model
{
    protected $fillable = [
        'tablo_project_id',
        'access_type',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the project this log belongs to.
     */
    public function tabloProject(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class);
    }

    /**
     * Log an access event.
     */
    public static function logAccess(
        int $projectId,
        string $accessType,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'tablo_project_id' => $projectId,
            'access_type' => $accessType,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'metadata' => $metadata,
        ]);
    }
}
