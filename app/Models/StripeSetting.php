<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeSetting extends Model
{
    protected $fillable = [
        'secret_key',
        'public_key',
        'webhook_secret',
        'is_test_mode',
        'is_active',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    protected $casts = [
        'secret_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'is_test_mode' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active Stripe settings
     */
    public static function active(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
