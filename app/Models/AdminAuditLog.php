<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'target_partner_id',
        'action',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Available actions
     */
    public const ACTION_VIEW = 'view';
    public const ACTION_CHARGE = 'charge';
    public const ACTION_CHANGE_PLAN = 'change_plan';
    public const ACTION_CANCEL_SUBSCRIPTION = 'cancel_subscription';

    /**
     * Get the admin user who performed the action
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the target partner
     */
    public function targetPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'target_partner_id');
    }

    /**
     * Create an audit log entry
     */
    public static function log(
        int $adminUserId,
        ?int $targetPartnerId,
        string $action,
        ?array $details = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'admin_user_id' => $adminUserId,
            'target_partner_id' => $targetPartnerId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }

    /**
     * Get action label in Hungarian
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_VIEW => 'Megtekintés',
            self::ACTION_CHARGE => 'Manuális terhelés',
            self::ACTION_CHANGE_PLAN => 'Csomag váltás',
            self::ACTION_CANCEL_SUBSCRIPTION => 'Előfizetés törlése',
            default => $this->action,
        };
    }
}
