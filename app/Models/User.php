<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasAccountLockout;
use App\Traits\HasTeamMembership;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser
{
    use SoftDeletes;
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_PHOTO_ADMIN = 'photo_admin';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_GUEST = 'guest';

    public const ROLE_TABLO = 'tablo';

    public const ROLE_MARKETER = 'marketer';

    // Csapattag szerepkörök (meghívottak)
    public const ROLE_DESIGNER = 'designer';
    public const ROLE_PRINTER = 'printer';
    public const ROLE_ASSISTANT = 'assistant';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasAccountLockout, HasApiTokens, HasFactory, HasTeamMembership, Notifiable, HasRoles;

    /**
     * The guard name for Spatie Permission.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * SECURITY NOTE: Critical fields like 'role', 'class_id', and 'tablo_partner_id'
     * are intentionally excluded to prevent privilege escalation attacks.
     * Use explicit assignment or dedicated methods to modify these fields.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'first_login_at',
        'password_set',
        'guest_token',
        // Auth fields
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that are not mass assignable.
     *
     * SECURITY: These fields require explicit assignment to prevent unauthorized
     * privilege escalation or relationship manipulation.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'role',
        'class_id',
        'tablo_partner_id',
        'email_verified_at',
        'created_at',
        'updated_at',
        // 2FA fields (sensitive)
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * SECURITY: Prevents exposure of sensitive data in API responses and logs.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'guest_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'address' => 'array',
            'first_login_at' => 'datetime',
            'password_set' => 'boolean',
            // Auth fields
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Check if this is the user's first login
     */
    public function isFirstLogin(): bool
    {
        return $this->first_login_at === null;
    }

    /**
     * Mark first login
     */
    public function markFirstLogin(): void
    {
        if ($this->isFirstLogin()) {
            $this->update(['first_login_at' => now()]);
        }
    }

    /**
     * Check if user has set their password
     */
    public function hasSetPassword(): bool
    {
        return $this->password_set === true;
    }

    /**
     * Get the class that the user belongs to.
     */
    public function class()
    {
        return $this->belongsTo(\App\Models\SchoolClass::class, 'class_id');
    }

    /**
     * Get the photos assigned to the user.
     */
    public function photos()
    {
        return $this->hasMany(\App\Models\Photo::class, 'assigned_user_id');
    }

    /**
     * Get the work sessions associated with the user.
     */
    public function workSessions(): BelongsToMany
    {
        return $this->belongsToMany(WorkSession::class, 'work_session_user')
            ->withTimestamps();
    }

    /**
     * Get the tablo workflow progress for this user.
     */
    public function tabloProgress(): HasOne
    {
        return $this->hasOne(TabloUserProgress::class);
    }

    /**
     * Get the tablo registration for this user.
     */
    public function tabloRegistration(): HasOne
    {
        return $this->hasOne(TabloRegistration::class);
    }

    /**
     * Get the tablo partner for this user.
     */
    public function tabloPartner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'tablo_partner_id');
    }

    /**
     * Get the partner account for this user.
     */
    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /**
     * Determine if the user has a super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Determine if the user has a photo admin role.
     */
    public function isPhotoAdmin(): bool
    {
        return $this->hasRole(self::ROLE_PHOTO_ADMIN);
    }

    /**
     * Determine if the user has a customer role.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    /**
     * Determine if the user has a guest role.
     */
    public function isGuest(): bool
    {
        // Check both role and name pattern (Guest users may not have the role assigned)
        return $this->hasRole(self::ROLE_GUEST) || str_starts_with($this->name, 'Guest-');
    }

    /**
     * Determine if the user has a marketer role.
     */
    public function isMarketer(): bool
    {
        return $this->hasRole(self::ROLE_MARKETER);
    }

    /**
     * Get the display name with email for select options.
     */
    public function getDisplayNameAttribute(): string
    {
        return strip_tags($this->name).' ('.$this->email.')';
    }

    /**
     * Get the title for Filament select options.
     */
    public function getFilamentTitleAttribute(): string
    {
        return strip_tags($this->name).' ('.$this->email.')';
    }

    /**
     * Get the name attribute, stripping any HTML tags.
     */
    public function getNameAttribute($value): string
    {
        return strip_tags($value);
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Refresh the model to ensure we have the latest data from DB
        if ($this->exists) {
            $this->refresh();
        }

        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_PHOTO_ADMIN,
            'tablo',
        ]);
    }

    // ==========================================
    // 2FA (PREPARATION - NOT YET IMPLEMENTED)
    // ==========================================

    /**
     * Check if 2FA is enabled for this user.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at !== null;
    }

    // ==========================================
    // LOGIN AUDITS
    // ==========================================

    /**
     * Get login audits for this user.
     */
    public function loginAudits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoginAudit::class);
    }
}
