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
    public const ACTION_SET_DISCOUNT = 'set_discount';
    public const ACTION_REMOVE_DISCOUNT = 'remove_discount';

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
     * Throttle duration in minutes for view actions
     */
    public const VIEW_THROTTLE_MINUTES = 5;

    /**
     * Create an audit log entry
     */
    public static function log(
        int $adminUserId,
        ?int $targetPartnerId,
        string $action,
        ?array $details = null,
        ?string $ipAddress = null
    ): ?self {
        // Throttle view actions - don't log if same admin viewed same partner within 5 minutes
        if ($action === self::ACTION_VIEW && $targetPartnerId !== null) {
            $recentView = self::where('admin_user_id', $adminUserId)
                ->where('target_partner_id', $targetPartnerId)
                ->where('action', self::ACTION_VIEW)
                ->where('created_at', '>=', now()->subMinutes(self::VIEW_THROTTLE_MINUTES))
                ->exists();

            if ($recentView) {
                return null; // Skip logging
            }
        }

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
            self::ACTION_SET_DISCOUNT => 'Kedvezmény beállítás',
            self::ACTION_REMOVE_DISCOUNT => 'Kedvezmény törlés',
            default => $this->action,
        };
    }
}
